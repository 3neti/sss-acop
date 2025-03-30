<?php

namespace App\KYC\Data;

use Spatie\LaravelData\Data;

class KYCMetadataData extends Data
{
    public function __construct(
        public string $requestId,
        public string $transactionId,
    ) {}
}
