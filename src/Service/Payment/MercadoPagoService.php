<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\User;
use Exception;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\InvalidArgumentException;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\Payment as MPPayment;
use MercadoPago\Resources\Preference;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class MercadoPagoService
{
    private const int STATUS_CACHE_TTL = 60; // 1 minute

    private PreferenceClient $preferenceClient;
    private PaymentClient $paymentClient;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        #[Autowire(env: 'MERCADOPAGO_ACCESS_TOKEN')]
        private readonly string $accessToken,
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'float:MERCADOPAGO_MARKETPLACE_FEE_PERCENT')]
        private readonly float $marketplaceFeePercent = 0.0,
    ) {
        MercadoPagoConfig::setAccessToken($this->accessToken);
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);

        $this->preferenceClient = new PreferenceClient();
        $this->paymentClient = new PaymentClient();
    }

    /**
     * Create a Marketplace payment preference.
     *
     * The preference is created using the driver's access token via RequestOptions
     * so that funds land in the driver's MP account. The platform fee (if any) is
     * deducted at the time of payment via marketplace_fee.
     *
     * @param Payment $payment
     * @param User $user The parent/payer
     * @param string $backUrl
     * @param string $notificationUrl
     * @param string $driverAccessToken Plaintext MP access token of the receiving driver
     * @return array{preference_id: string, init_point: string, sandbox_init_point: string|null}
     * @throws Exception
     */
    public function createPreference(
        Payment $payment,
        User $user,
        string $backUrl,
        string $notificationUrl,
        string $driverAccessToken,
    ): array {
        try {
            $items = [];
            foreach ($payment->getStudents() as $student) {
                $items[] = [
                    'id'          => (string) $student->getId(),
                    'title'       => "Transportation for {$student->getFirstName()} {$student->getLastName()}",
                    'description' => $payment->getDescription() ?? 'School transportation service',
                    'quantity'    => 1,
                    'currency_id' => $payment->getCurrency(),
                    'unit_price'  => round((float) $payment->getAmount() / $payment->getStudents()->count(), 2),
                ];
            }

            $marketplaceFee = $this->calculateMarketplaceFee((float) $payment->getAmount());

            $preferenceData = [
                'items'      => $items,
                'payer'      => [
                    'name'    => $user->getFirstName(),
                    'surname' => $user->getLastName(),
                    'email'   => $user->getEmail(),
                    'phone'   => ['number' => $user->getPhoneNumber()],
                ],
                'back_urls'  => [
                    'success' => $backUrl . '?status=success',
                    'failure' => $backUrl . '?status=failure',
                    'pending' => $backUrl . '?status=pending',
                ],
                'auto_return'          => 'approved',
                'notification_url'     => $notificationUrl,
                'external_reference'   => (string) $payment->getId(),
                'statement_descriptor' => 'SCHOOL_TRANSPORT',
                'marketplace'          => 'MP',
                'marketplace_fee'      => $marketplaceFee,
                'expires'              => true,
                'expiration_date_from' => (new \DateTime())->format('c'),
                'expiration_date_to'   => $payment->getExpiresAt()?->format('c')
                    ?? (new \DateTime('+24 hours'))->format('c'),
            ];

            // RequestOptions carries the driver's access token for this single call.
            // It overrides the global platform token without mutating MercadoPagoConfig,
            // so concurrent requests for different drivers remain isolated.
            $requestOptions = new RequestOptions($driverAccessToken);

            $this->logger->info('Creating Mercado Pago marketplace preference', [
                'payment_id'       => $payment->getId(),
                'user_id'          => $user->getId(),
                'driver_id'        => $payment->getDriver()?->getId(),
                'amount'           => $payment->getAmount(),
                'marketplace_fee'  => $marketplaceFee,
            ]);

            $preference = $this->preferenceClient->create($preferenceData, $requestOptions);

            $this->logger->info('Mercado Pago preference created', [
                'payment_id'    => $payment->getId(),
                'preference_id' => $preference->id,
            ]);

            return [
                'preference_id'       => $preference->id,
                'init_point'          => $preference->init_point,
                'sandbox_init_point'  => $preference->sandbox_init_point ?? null,
            ];
        } catch (MPApiException $e) {
            $this->logger->error('Mercado Pago API error creating preference', [
                'payment_id'   => $payment->getId(),
                'error'        => $e->getMessage(),
                'api_response' => $e->getApiResponse(),
            ]);

            throw new \RuntimeException('Failed to create payment preference: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->logger->error('Error creating Mercado Pago preference', [
                'payment_id' => $payment->getId(),
                'error'      => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to create payment preference', 0, $e);
        }
    }

    private function calculateMarketplaceFee(float $amount): float
    {
        if ($this->marketplaceFeePercent <= 0.0) {
            return 0.0;
        }

        return round($amount * $this->marketplaceFeePercent / 100, 2);
    }

    /**
     * Get payment status from Mercado Pago
     *
     * @param string $paymentProviderId Mercado Pago payment ID
     * @return array Payment details
     * @throws Exception|\Psr\Cache\InvalidArgumentException
     */
    public function getPaymentStatus(string $paymentProviderId): array
    {
        $cacheKey = "mp_payment_status:{$paymentProviderId}";

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($paymentProviderId) {
                $item->expiresAfter(self::STATUS_CACHE_TTL);

                $this->logger->info('Fetching payment status from Mercado Pago', [
                    'payment_provider_id' => $paymentProviderId,
                ]);

                $payment = $this->paymentClient->get((int) $paymentProviderId);

                return $this->mapPaymentToArray($payment);
            });
        } catch (MPApiException $e) {
            $this->logger->error('Mercado Pago API error fetching payment status', [
                'payment_provider_id' => $paymentProviderId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to fetch payment status: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get full payment details
     */
    public function getPaymentDetails(string $paymentProviderId): MPPayment
    {
        try {
            return $this->paymentClient->get((int) $paymentProviderId);
        } catch (MPApiException $e) {
            $this->logger->error('Mercado Pago API error fetching payment details', [
                'payment_provider_id' => $paymentProviderId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to fetch payment details', 0, $e);
        }
    }

    /**
     * Refund payment (full or partial)
     *
     * @param string $paymentProviderId
     * @param float|null $amount Null for full refund
     * @return array Refund details
     * @throws Exception|\Psr\Cache\InvalidArgumentException
     */
    public function refundPayment(string $paymentProviderId, ?float $amount = null): array
    {
        try {
            $this->logger->info('Processing Mercado Pago refund', [
                'payment_provider_id' => $paymentProviderId,
                'amount' => $amount,
            ]);

            $refundData = [];
            if ($amount !== null) {
                $refundData['amount'] = $amount;
            }

            $refund = $this->paymentClient->refund((int) $paymentProviderId, $refundData);

            $this->logger->info('Mercado Pago refund processed', [
                'payment_provider_id' => $paymentProviderId,
                'refund_id' => $refund->id,
                'status' => $refund->status,
            ]);

            // Invalidate cached payment status
            $this->cache->delete("mp_payment_status:{$paymentProviderId}");

            return [
                'refund_id' => $refund->id,
                'status' => $refund->status,
                'amount' => $refund->amount,
            ];
        } catch (MPApiException $e) {
            $this->logger->error('Mercado Pago API error processing refund', [
                'payment_provider_id' => $paymentProviderId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to process refund: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get payments by date range for reconciliation
     *
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     * @return array
     */
    public function getPaymentsByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        try {
            $this->logger->info('Fetching payments from Mercado Pago for reconciliation', [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ]);

            // Note: Mercado Pago SDK doesn't have a direct method for this
            // You may need to implement via an HTTP client directly
            // This is a placeholder implementation

            return [];
        } catch (Exception $e) {
            $this->logger->error('Error fetching payments for reconciliation', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to fetch payments for reconciliation', 0, $e);
        }
    }

    /**
     * Map Mercado Pago payment object to array
     */
    private function mapPaymentToArray(MPPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'status_detail' => $payment->status_detail,
            'payment_method_id' => $payment->payment_method_id,
            'payment_type_id' => $payment->payment_type_id,
            'transaction_amount' => $payment->transaction_amount,
            'currency_id' => $payment->currency_id,
            'date_created' => $payment->date_created,
            'date_approved' => $payment->date_approved,
            'external_reference' => $payment->external_reference,
            'payer' => [
                'id' => $payment->payer?->id,
                'email' => $payment->payer?->email,
            ],
        ];
    }

    /**
     * Clear payment status cache
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function clearPaymentCache(string $paymentProviderId): void
    {
        $this->cache->delete("mp_payment_status:{$paymentProviderId}");
    }
}
