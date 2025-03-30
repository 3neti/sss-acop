<?php

namespace App\KYC\Events;

use App\KYC\Data\KYCResultData;
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
