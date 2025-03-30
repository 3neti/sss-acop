<?php

namespace App\KYC\Commands;

use Illuminate\Support\Facades\Log;
use App\KYC\Actions\GenerateLink;
use Illuminate\Console\Command;
use Exception;

class GenerateKYCLink extends Command
{
    protected $signature = 'kyc:generate-link
        {transactionId : Unique transaction ID}
        {--workflowId= : Optional workflow ID override}';

    protected $description = 'Generate a KYC onboarding link using the Hyperverge API.';

    public function handle(): int
    {
        $transactionId = $this->argument('transactionId');
        $workflowId = $this->option('workflowId') ?? 'onboarding';

        try {
            $url = GenerateLink::get($transactionId, null, [
                'workflowId' => $workflowId
            ]);

            $this->info("✅ KYC link generated:");
            $this->line($url);

            Log::info('[GenerateKYCLink] Success', [
                'transactionId' => $transactionId,
                'workflowId' => $workflowId,
                'startKycUrl' => $url,
            ]);

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error("❌ Failed to generate KYC link: " . $e->getMessage());

            Log::error('[GenerateKYCLink] Failed', [
                'transactionId' => $transactionId,
                'workflowId' => $workflowId,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
