<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;
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
