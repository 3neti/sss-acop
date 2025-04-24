<?php

namespace App\Commerce\Console\Commands;

use App\KYC\Support\ParsedKYCResult;
use App\KYC\Actions\FetchKYCResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use App\Models\User;

class CreateSystemUserFromKYC extends Command
{
    protected $signature = 'user:create-system {transactionId=test-aa537-001} {amount=10000000} {--dry}';
    protected $description = 'Create the system user from a verified KYC result and optionally top-up wallet';

    public function handle(): int
    {
        $transactionId = $this->argument('transactionId');
        $amount = (float) $this->argument('amount');
        $isDryRun = $this->option('dry');

        $this->info("Fetching KYC result for transaction: {$transactionId}");

        try {
            $parsed = FetchKYCResult::run($transactionId);

            if (! $parsed instanceof ParsedKYCResult) {
                $this->error('Parsed KYC result is invalid.');
                return self::FAILURE;
            }

            $user = User::where('id_type', config('sss-acop.system.user.id_type'))
                ->where('id_value', config('sss-acop.system.user.id_value'))
                ->firstOrFail();

            $this->info("User [{$user->name}] found with ID type/number: {$parsed->idType()}: {$parsed->idValue()}");

            // Promote to system type if needed
            if ($user->type !== 'system') {
                $user->update(['type' => 'system']);
                $this->info("User [{$user->id}] promoted to type: system");
            } else {
                $this->info("User is already marked as system.");
            }

            // Dry run support
            if ($isDryRun) {
                $this->warn("💡 Dry-run mode: Skipping deposit of ₱" . number_format($amount, 2));
                return self::SUCCESS;
            }

            // Confirmation in production
            if (app()->isProduction()) {
                $confirmed = $this->confirm("You're in PRODUCTION. Proceed to deposit ₱" . number_format($amount, 2) . " to system user?");
                if (! $confirmed) {
                    $this->warn('❌ Aborted by user.');
                    return self::INVALID;
                }
            }

            // Perform deposit
            $user->depositFloat($amount, [
                'description' => 'Initial system wallet top-up',
                'source' => 'console:CreateSystemUserFromKYC',
            ]);

            $this->info("✅ Wallet credited with ₱" . number_format($amount, 2));

            return self::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('[CreateSystemUserFromKYC] Failed', [
                'transactionId' => $transactionId,
                'message' => $e->getMessage(),
            ]);

            $this->error("Error: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
