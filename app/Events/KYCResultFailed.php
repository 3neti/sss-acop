<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KYCResultFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $transactionId,
        public array $errorDetails = []
    ) {}
}
