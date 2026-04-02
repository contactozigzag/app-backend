<?php

declare(strict_types=1);

namespace App\State\PushDevice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PushDevice;
use App\Entity\User;
use App\Repository\PushDeviceRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Handles DELETE /api/push-devices/{id}.
 *
 * Deactivates the device if it belongs to the authenticated user.
 * Silently succeeds (204) when the device does not exist or belongs to another user,
 * so clients can always unregister without knowing ownership state.
 *
 * @implements ProcessorInterface<PushDevice, void>
 */
final readonly class DeactivatePushDeviceProcessor implements ProcessorInterface
{
    public function __construct(
        private PushDeviceRepository $deviceRepo,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $rawId = $uriVariables['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : 0;

        $device = $this->deviceRepo->find($id);

        if ($device instanceof PushDevice && $device->getUserId() === $user->getId()) {
            $device->deactivate();
            $this->deviceRepo->save($device);
        }
    }
}
