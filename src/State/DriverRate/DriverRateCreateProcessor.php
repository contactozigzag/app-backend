<?php

declare(strict_types=1);

namespace App\State\DriverRate;

use App\Enum\PricingModel;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Driver;
use App\Entity\DriverRate;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<DriverRate, DriverRate>
 */
final readonly class DriverRateCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param DriverRate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DriverRate
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $driver = $data->getDriver();

        if (! $driver instanceof Driver) {
            throw new UnprocessableEntityHttpException('Driver is required.');
        }

        // Verify the authenticated user owns this driver profile
        if ($driver->getUser() !== $user) {
            throw new AccessDeniedHttpException('You can only create rates for your own driver profile.');
        }

        // Verify route belongs to this driver (if set)
        $route = $data->getRoute();
        if ($route !== null && $route->getDriver() !== $driver) {
            throw new UnprocessableEntityHttpException('The specified route does not belong to this driver.');
        }

        // Auto-set the driver's pricing model if not yet configured
        $pricingModel = $data->getPricingModel();
        if ($pricingModel !== null && !$driver->getPricingModel() instanceof PricingModel) {
            $driver->setPricingModel($pricingModel);
        }

        // Ensure rate's pricing model matches driver's pricing model
        $driverPricingModel = $driver->getPricingModel();
        if ($pricingModel !== null && $driverPricingModel instanceof PricingModel && $driverPricingModel !== $pricingModel) {
            throw new UnprocessableEntityHttpException(
                sprintf(
                    'Rate pricing model "%s" does not match the driver\'s pricing model "%s".',
                    $pricingModel->value,
                    $driverPricingModel->value,
                ),
            );
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
