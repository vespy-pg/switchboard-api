<?php

namespace App\Event;

final readonly class UserCreatedEvent
{
    public function __construct(
        public string $createdUserId,
        public ?string $userId, // creator, system = null
    ) {
    }
}
