<?php

declare(strict_types=1);

namespace App\Contract;

use App\Entity\Address;

/**
 * Implemented by entities that hold an optional postal address
 * and expose setAddress() — used by AddressFormTrait in EasyAdmin controllers.
 */
interface HasAddressInterface
{
    public function setAddress(?Address $address): static;
}
