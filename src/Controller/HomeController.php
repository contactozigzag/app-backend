<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/payment/result', name: 'app_payment_result')]
    public function paymentResult(Request $request): Response
    {
        // MP appends external_reference (our internal payment ID) to the back_url.
        // Use it to read the authoritative status from DB rather than trusting
        // the ?status= param, which reflects MP's state at redirect time and can
        // be 'failure' even when the payment is ultimately approved.
        $externalRef = $request->query->get('external_reference');
        $payment = is_numeric($externalRef) ? $this->paymentRepository->find((int) $externalRef) : null;

        if ($payment !== null) {
            $status = match ($payment->getStatus()) {
                PaymentStatus::APPROVED => 'success',
                PaymentStatus::PENDING, PaymentStatus::PROCESSING => 'pending',
                default => 'failure',
            };
        } else {
            $status = $request->query->getString('status', 'failure');
        }

        $titles = [
            'success' => 'Pago exitoso',
            'pending' => 'Pago pendiente',
            'failure' => 'Pago fallido',
        ];

        return $this->render('payment/result.html.twig', [
            'status' => $status,
            'title' => $titles[$status] ?? $titles['failure'],
        ]);
    }
}
