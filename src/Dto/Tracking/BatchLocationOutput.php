<?php

declare(strict_types=1);

namespace App\Dto\Tracking;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class BatchLocationOutput
{
    public function __construct(
        #[Groups(['tracking:batch:read'])]
        public bool $success,
        #[Groups(['tracking:batch:read'])]
        public int $processedCount,
        #[Groups(['tracking:batch:read'])]
        public int $totalCount,

        /**
         * @var string[]
         */
        #[Groups(['tracking:batch:read'])]
        public array $errors,
    ) {
    }
}
