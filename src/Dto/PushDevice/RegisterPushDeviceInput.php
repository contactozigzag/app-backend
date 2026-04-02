<?php

declare(strict_types=1);

namespace App\Dto\PushDevice;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterPushDeviceInput
{
    /**
     * Expo push token for the device (e.g. ExponentPushToken[xxxxxxxx]).
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $expoPushToken;

    /**
     * Mobile platform: "ios" or "android".
     */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['ios', 'android'])]
    #[Assert\Length(max: 10)]
    public string $platform;

    /**
     * Optional human-readable device name (e.g. "iPhone 15 Pro").
     */
    #[Assert\Length(max: 255)]
    public ?string $deviceName = null;

    /**
     * Optional OS version string (e.g. "17.4").
     */
    #[Assert\Length(max: 20)]
    public ?string $osVersion = null;
}
