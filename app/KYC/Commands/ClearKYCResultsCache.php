<?php

namespace App\KYC\Commands;

use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class ClearKYCResultsCache extends Command
{
    protected $signature = 'kyc:clear-cache {--transactionId= : Clear only the specified transactionId}';
    protected $description = 'Clear cached KYC results via cache_context helper.';

    protected string $prefix = 'kyc_result';
    protected string $tag = 'kyc_results';

    public function handle(): int
    {
        $transactionId = $this->option('transactionId');
        $context = cache_context($this->prefix)->tag($this->tag);

        if ($transactionId) {
            $context->clear($transactionId);

            $msg = "🧹 Cleared cache for transactionId: {$transactionId}";
            $this->info($msg);
            Log::info('[ClearKYCResultsCache] ' . $msg, compact('transactionId'));

            return self::SUCCESS;
        }

        $didFlush = $context->flush();

        if ($didFlush) {
            $msg = '✅ KYC cache flushed (tagged)';
        } else {
            $msg = '⚠️ flush() skipped: tags not supported or not set — nothing was flushed.';
        }

        $this->info($msg);
        Log::info('[ClearKYCResultsCache] ' . $msg);

        return self::SUCCESS;
    }
}
