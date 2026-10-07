<?php

namespace App\Review;

final readonly class AwardedTitle
{
    public function __construct(
        public string $name,
        public string $reason,
    ) {
    }
}
