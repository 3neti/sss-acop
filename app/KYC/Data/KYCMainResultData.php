<?php

namespace App\Data;

namespace App\KYC\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\{Data, DataCollection};

class KYCMainResultData extends Data
{
    public function __construct(
        public KYCWorkflowDetailsData $workflowDetails,
        public string $applicationStatus,
        #[DataCollectionOf(KYCModuleResultData::class)]
        public DataCollection $results,
    ) {}
}
