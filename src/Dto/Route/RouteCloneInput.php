<?php

declare(strict_types=1);

namespace App\Dto\Route;

final class RouteCloneInput
{
    public ?string $name = null;

    public bool $isActive = false;

    public bool $isTemplate = false;
}
