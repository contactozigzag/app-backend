<?php

declare(strict_types=1);

namespace App\Dto\Chat;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class ChatMessageIdOutput
{
    public function __construct(
        #[Groups(['chat:message:read'])]
        public int $id,
    ) {
    }
}
