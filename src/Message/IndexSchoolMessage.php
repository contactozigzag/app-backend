<?php

declare(strict_types=1);

namespace App\Message;

final readonly class IndexSchoolMessage
{
    public function __construct(
        public int $schoolId,
    ) {
    }
}
