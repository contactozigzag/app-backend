<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Zenstruck\Messenger\Monitor\Controller\MessengerMonitorController as BaseController;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/admin/messenger')]
class MessengerMonitorController extends BaseController
{
}
