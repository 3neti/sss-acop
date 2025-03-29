<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class HypervergeWebhookData extends Data
{
    public function __construct(
        public string $status,
        public int $statusCode,
        public array $metadata,
        public array $result
    ) {}
}
