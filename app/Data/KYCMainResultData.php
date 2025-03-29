<?php

namespace App\Data;

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;

class KYCMainResultData extends Data
{
    public function __construct(
        public KYCWorkflowDetailsData $workflowDetails,
        public string $applicationStatus,
        #[DataCollectionOf(KYCModuleResultData::class)]
        public DataCollection $results,
    ) {}
}
