<?php

declare(strict_types=1);

namespace App\State\DriverRate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\DriverRate;
use App\Repository\DriverRateRepository;
use App\Repository\DriverRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<DriverRate>
 */
final readonly class DriverRateCollectionProvider implements ProviderInterface
{
    public function __construct(
        private DriverRateRepository $driverRateRepository,
        private DriverRepository $driverRepository,
    ) {
    }

    /**
     * @return DriverRate[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $context['request'] instanceof Request ? $context['request'] : null;
        $driverId = $request?->query->get('driver');

        if ($driverId === null) {
            return [];
        }

        $driver = $this->driverRepository->find((int) $driverId);

        if ($driver === null) {
            throw new NotFoundHttpException(sprintf('Driver %d not found.', (int) $driverId));
        }

        return $this->driverRateRepository->findByDriver($driver);
    }
}
