<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Dto\Payment\CalculatedRate;
use App\Entity\Driver;
use App\Entity\DriverRate;
use App\Entity\Route;
use App\Enum\PricingModel;
use App\Repository\DriverRateRepository;
use InvalidArgumentException;

final readonly class DriverRateCalculator
{
    public function __construct(
        private DriverRateRepository $driverRateRepository,
    ) {
    }

    public function calculateAmount(Driver $driver, ?Route $route, int $studentCount): CalculatedRate
    {
        $pricingModel = $driver->getPricingModel();

        if (! $pricingModel instanceof PricingModel) {
            throw new InvalidArgumentException('Driver has no pricing model configured.');
        }

        if ($pricingModel->requiresRoute() && ! $route instanceof Route) {
            throw new InvalidArgumentException(
                sprintf('Route is required for "%s" pricing model.', $pricingModel->label()),
            );
        }

        $lookupRoute = $pricingModel->requiresRoute() ? $route : null;
        $rate = $this->driverRateRepository->findByDriverAndRoute($driver, $lookupRoute);

        if (! $rate instanceof DriverRate) {
            $routeInfo = $route instanceof Route ? sprintf(' and route "%s" (ID %d)', $route->getName(), $route->getId()) : '';
            throw new InvalidArgumentException(
                sprintf('No rate configured for driver "%s"%s.', $driver->getNickname(), $routeInfo),
            );
        }

        if ($pricingModel->usesPerStudentAmount()) {
            $perStudentAmount = $rate->getPerStudentAmount();
            if ($perStudentAmount === null) {
                throw new InvalidArgumentException('Per-student amount is not set on the rate.');
            }

            $amount = number_format((float) $perStudentAmount * $studentCount, 2, '.', '');

            return new CalculatedRate(
                amount: $amount,
                currency: $rate->getCurrency(),
                rateSnapshot: [
                    'pricingModel' => $pricingModel->value,
                    'routeId' => $route?->getId(),
                    'routeName' => $route?->getName(),
                    'perStudentAmount' => $perStudentAmount,
                    'studentCount' => $studentCount,
                    'calculatedAmount' => $amount,
                ],
            );
        }

        $flatAmount = $rate->getAmount();
        if ($flatAmount === null) {
            throw new InvalidArgumentException('Amount is not set on the rate.');
        }

        return new CalculatedRate(
            amount: $flatAmount,
            currency: $rate->getCurrency(),
            rateSnapshot: [
                'pricingModel' => $pricingModel->value,
                'routeId' => $route?->getId(),
                'routeName' => $route?->getName(),
                'amount' => $flatAmount,
                'studentCount' => $studentCount,
                'calculatedAmount' => $flatAmount,
            ],
        );
    }
}
