<?php

declare(strict_types=1);

namespace App\State\Tracking;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Tracking\BatchLocationInput;
use App\Dto\Tracking\BatchLocationOutput;
use App\Entity\LocationUpdate;
use App\Repository\DriverRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles POST /api/tracking/location/batch.
 *
 * Batch-ingests offline GPS points for a driver. Each item is individually
 * validated; failures are collected and returned in the `errors` field.
 *
 * @implements ProcessorInterface<BatchLocationInput, BatchLocationOutput>
 */
final readonly class BatchLocationProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DriverRepository $driverRepository,
    ) {
    }

    /**
     * @param BatchLocationInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BatchLocationOutput
    {
        $driver = $this->driverRepository->find((int) $data->driverId);

        if ($driver === null) {
            throw new NotFoundHttpException('Driver not found.');
        }

        $locations = $data->locations ?? [];
        $processedCount = 0;
        $errors = [];

        foreach ($locations as $index => $locationData) {
            try {
                $lat = $locationData['latitude'] ?? null;
                $lng = $locationData['longitude'] ?? null;

                if (! is_numeric($lat) || ! is_numeric($lng)) {
                    $errors[] = sprintf('Location at index %s missing latitude or longitude', $index);
                    continue;
                }

                $location = new LocationUpdate();
                $location->setDriver($driver);
                $location->setLatitude((string) $lat);
                $location->setLongitude((string) $lng);

                $ts = $locationData['timestamp'] ?? null;
                $location->setTimestamp(
                    is_string($ts)
                        ? new DateTimeImmutable($ts)
                        : new DateTimeImmutable()
                );

                $speed = $locationData['speed'] ?? null;
                if (is_numeric($speed)) {
                    $location->setSpeed((string) $speed);
                }

                $heading = $locationData['heading'] ?? null;
                if (is_numeric($heading)) {
                    $location->setHeading((string) $heading);
                }

                $accuracy = $locationData['accuracy'] ?? null;
                if (is_numeric($accuracy)) {
                    $location->setAccuracy((string) $accuracy);
                }

                $this->entityManager->persist($location);
                $processedCount++;
            } catch (Exception $e) {
                $errors[] = sprintf('Error processing location at index %s: ', $index) . $e->getMessage();
            }
        }

        $this->entityManager->flush();

        return new BatchLocationOutput(
            success: true,
            processedCount: $processedCount,
            totalCount: count($locations),
            errors: $errors,
        );
    }
}
