<?php

namespace App\Events;

use App\Data\KYCResultData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KYCResultFetched
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $transactionId,
        public KYCResultData $data
    ) {}
}
