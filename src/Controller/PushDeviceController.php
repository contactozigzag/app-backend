<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PushDevice;
use App\Entity\User;
use App\Repository\PushDeviceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PushDeviceController extends AbstractController
{
    public function __construct(
        private readonly PushDeviceRepository $deviceRepo,
    ) {
    }

    #[Route('/api/devices', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $request->toArray();
        $expoPushToken = (string) ($data['expoPushToken'] ?? '');
        $platform = (string) ($data['platform'] ?? '');

        if ($expoPushToken === '' || $platform === '') {
            return new JsonResponse([
                'error' => 'expoPushToken and platform are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $device = $this->deviceRepo->findByToken($expoPushToken);

        if ($device instanceof PushDevice) {
            $device->updateUser($user);
            $device->touch();
        } else {
            $device = new PushDevice(
                user: $user,
                expoPushToken: $expoPushToken,
                platform: $platform,
                deviceName: isset($data['deviceName']) ? (string) $data['deviceName'] : null,
                osVersion: isset($data['osVersion']) ? (string) $data['osVersion'] : null,
            );
        }

        $this->deviceRepo->save($device);

        return new JsonResponse([
            'id' => $device->getId(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/devices/{id}', methods: ['DELETE'])]
    public function unregister(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $device = $this->deviceRepo->find($id);

        if ($device instanceof PushDevice && $device->getUserId() === $user->getId()) {
            $device->deactivate();
            $this->deviceRepo->save($device);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
