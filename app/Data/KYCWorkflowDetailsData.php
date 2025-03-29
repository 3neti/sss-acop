<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class KYCWorkflowDetailsData extends Data
{
    public function __construct(
        public string $workflowId,
        public int $version,
    ) {}
}
