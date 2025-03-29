<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class KYCModuleApiResponseData extends Data
{
    public function __construct(
        public string $status,
        public int $statusCode,
        public KYCMetadataData $metadata,
        public ?array $result = [], // flexible structure per module type
    ) {}
}
