<?php

declare(strict_types=1);

namespace App\Message;

final readonly class RemoveSchoolFromIndexMessage
{
    public function __construct(
        public int $schoolId,
    ) {
    }
}
