<?php

namespace App\DTO;

class ModerateMessageDto
{
    public function __construct(
        public bool $isAllowed,

        public string $explanation,
    ) {}
}
