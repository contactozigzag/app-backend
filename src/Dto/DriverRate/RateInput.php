<?php

declare(strict_types=1);

namespace App\Dto\DriverRate;

use Symfony\Component\Validator\Constraints as Assert;

final class RateInput
{
    #[Assert\Positive]
    public ?int $routeId = null;

    #[Assert\Positive]
    public ?string $amount = null;

    #[Assert\Positive]
    public ?string $perStudentAmount = null;

    #[Assert\Currency]
    public string $currency = 'ARS';
}
