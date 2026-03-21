<?php

declare(strict_types=1);

namespace App\Dto\DriverRate;

use App\Enum\PricingModel;
use Symfony\Component\Validator\Constraints as Assert;

final class SetDriverRatesInput
{
    #[Assert\NotNull]
    public ?PricingModel $pricingModel = null;

    /**
     * @var RateInput[]
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1, minMessage: 'At least one rate is required.')]
    #[Assert\Valid]
    public array $rates = [];
}
