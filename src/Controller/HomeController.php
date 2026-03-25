<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/payment/result', name: 'app_payment_result')]
    public function paymentResult(Request $request): Response
    {
        $status = $request->query->getString('status', 'failure');

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
