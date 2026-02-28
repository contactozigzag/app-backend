<?php

declare(strict_types=1);

namespace App\Dto\Chat;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class ChatMessageListOutput
{
    public function __construct(
        #[Groups(['chat:messages:read'])]
        public string $alertId,
        #[Groups(['chat:messages:read'])]
        public int $page,
        #[Groups(['chat:messages:read'])]
        public int $limit,
        #[Groups(['chat:messages:read'])]
        public int $count,

        /**
         * @var list<array{id: int|null, sender: array{id: int|null, name: string}, content: string, sentAt: string, readBy: int[]}>
         */
        #[Groups(['chat:messages:read'])]
        public array $messages,
    ) {
    }
}
