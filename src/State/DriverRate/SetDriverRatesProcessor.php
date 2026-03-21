<?php

declare(strict_types=1);

namespace App\State\DriverRate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\DriverRate\SetDriverRatesInput;
use App\Entity\Driver;
use App\Entity\DriverRate;
use App\Entity\User;
use App\Repository\DriverRepository;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles POST /api/drivers/{id}/rates — atomically replaces all rates for a driver.
 *
 * @implements ProcessorInterface<SetDriverRatesInput, Driver>
 */
final readonly class SetDriverRatesProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DriverRepository $driverRepository,
        private RouteRepository $routeRepository,
        private Security $security,
    ) {
    }

    /**
     * @param SetDriverRatesInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Driver
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $driverId = $uriVariables['id'] ?? null;
        if (! is_numeric($driverId)) {
            throw new NotFoundHttpException('Driver not found.');
        }

        $driver = $this->driverRepository->find((int) $driverId);
        if ($driver === null) {
            throw new NotFoundHttpException(sprintf('Driver %d not found.', (int) $driverId));
        }

        if ($driver->getUser() !== $user) {
            throw new AccessDeniedHttpException('You can only set rates for your own driver profile.');
        }

        $pricingModel = $data->pricingModel;
        if ($pricingModel === null) {
            throw new UnprocessableEntityHttpException('Pricing model is required.');
        }

        // Validate rate rows match the pricing model
        foreach ($data->rates as $rateInput) {
            if ($pricingModel->requiresRoute() && $rateInput->routeId === null) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Route is required for each rate with "%s" pricing model.', $pricingModel->label()),
                );
            }

            if (! $pricingModel->requiresRoute() && $rateInput->routeId !== null) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Route must not be set for "%s" pricing model.', $pricingModel->label()),
                );
            }

            if ($pricingModel->usesPerStudentAmount() && ($rateInput->perStudentAmount === null || $rateInput->perStudentAmount === '')) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Per-student amount is required for "%s" pricing model.', $pricingModel->label()),
                );
            }

            if (! $pricingModel->usesPerStudentAmount() && ($rateInput->amount === null || $rateInput->amount === '')) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Amount is required for "%s" pricing model.', $pricingModel->label()),
                );
            }
        }

        // For non-route models, only 1 rate row is expected
        if (! $pricingModel->requiresRoute() && count($data->rates) > 1) {
            throw new UnprocessableEntityHttpException(
                sprintf('Only one rate row is allowed for "%s" pricing model.', $pricingModel->label()),
            );
        }

        // Remove existing rates
        foreach ($driver->getRates()->toArray() as $existingRate) {
            $driver->removeRate($existingRate);
            $this->entityManager->remove($existingRate);
        }

        // Set the pricing model
        $driver->setPricingModel($pricingModel);

        // Create new rates
        foreach ($data->rates as $rateInput) {
            $rate = new DriverRate();
            $rate->setDriver($driver);
            $rate->setPricingModel($pricingModel);
            $rate->setCurrency($rateInput->currency);

            if ($rateInput->routeId !== null) {
                $route = $this->routeRepository->find($rateInput->routeId);
                if ($route === null) {
                    throw new NotFoundHttpException(sprintf('Route %d not found.', $rateInput->routeId));
                }

                if ($route->getDriver() !== $driver) {
                    throw new UnprocessableEntityHttpException(
                        sprintf('Route %d does not belong to this driver.', $rateInput->routeId),
                    );
                }

                $rate->setRoute($route);
            }

            if ($pricingModel->usesPerStudentAmount()) {
                $rate->setPerStudentAmount($rateInput->perStudentAmount);
            } else {
                $rate->setAmount($rateInput->amount);
            }

            $driver->addRate($rate);
            $this->entityManager->persist($rate);
        }

        $this->entityManager->flush();

        return $driver;
    }
}
