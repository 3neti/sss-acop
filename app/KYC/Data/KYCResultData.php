<?php

namespace App\KYC\Data;

use Spatie\LaravelData\Data;

class KYCResultData extends Data
{
    public function __construct(
        public string $status,
        public int $statusCode,
        public KYCMetadataData $metadata,
        public KYCMainResultData $result,
    ) {}
}
