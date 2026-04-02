<?php

declare(strict_types=1);

namespace App\Dto\PushDevice;

final readonly class PushDeviceOutput
{
    public function __construct(
        public int $id,
        public string $expoPushToken,
        public string $platform,
        public ?string $deviceName,
        public ?string $osVersion,
    ) {
    }
}
