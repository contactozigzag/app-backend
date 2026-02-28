<?php

declare(strict_types=1);

namespace App\Dto\Chat;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChatMessageInput
{
    public function __construct(
        #[Groups(['chat:message:write'])]
        #[Assert\NotBlank]
        public string $content = '',
    ) {
    }
}
