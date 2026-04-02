<?php

declare(strict_types=1);

namespace App\State\PushDevice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PushDevice\PushDeviceOutput;
use App\Dto\PushDevice\RegisterPushDeviceInput;
use App\Entity\PushDevice;
use App\Entity\User;
use App\Repository\PushDeviceRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Handles POST /api/push-devices.
 *
 * Upserts by Expo push token: if the token already exists, the owning user
 * and last-seen timestamp are updated; otherwise a new device record is created.
 *
 * @implements ProcessorInterface<RegisterPushDeviceInput, PushDeviceOutput>
 */
final readonly class RegisterPushDeviceProcessor implements ProcessorInterface
{
    public function __construct(
        private PushDeviceRepository $deviceRepo,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PushDeviceOutput
    {
        /** @var RegisterPushDeviceInput $data */
        /** @var User $user */
        $user = $this->security->getUser();

        $device = $this->deviceRepo->findByToken($data->expoPushToken);

        if ($device instanceof PushDevice) {
            $device->updateUser($user);
            $device->touch();
        } else {
            $device = new PushDevice(
                user: $user,
                expoPushToken: $data->expoPushToken,
                platform: $data->platform,
                deviceName: $data->deviceName,
                osVersion: $data->osVersion,
            );
        }

        $this->deviceRepo->save($device);

        return new PushDeviceOutput(
            id: (int) $device->getId(),
            expoPushToken: $device->getExpoPushToken(),
            platform: $device->getPlatform(),
            deviceName: $device->getDeviceName(),
            osVersion: $device->getOsVersion(),
        );
    }
}
