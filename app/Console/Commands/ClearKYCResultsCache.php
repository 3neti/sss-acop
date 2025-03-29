<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command as CommandAlias;
use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Console\Command;

class ClearKYCResultsCache extends Command
{
    protected $signature = 'kyc:clear-cache {--transactionId= : Clear only the specified transactionId}';
    protected $description = 'Clear cached KYC results. Supports both tagged and manual caching.';

    protected string $tag = 'kyc_results';
    protected string $manualRegistryKey = 'kyc_result_keys';
    protected string $cacheKeyPrefix = 'kyc_result:';

    public function handle(): int
    {
        $transactionId = $this->option('transactionId');
        $supportsTags = $this->supportsCacheTags();

        if ($transactionId) {
            $key = $this->cacheKeyPrefix . $transactionId;

            if ($supportsTags) {
                $deleted = Cache::tags([$this->tag])->forget($key);
            } else {
                $deleted = Cache::forget($key);

                // Clean up from manual registry
                $keys = Cache::get($this->manualRegistryKey, []);
                $keys = array_filter($keys, fn($k) => $k !== $key);
                Cache::forever($this->manualRegistryKey, array_values($keys));
            }

            $msg = $deleted
                ? "🧹 Cleared cache for transactionId: {$transactionId}"
                : "⚠️ No cache found for transactionId: {$transactionId}";

            $this->info($msg);
            Log::info('[ClearKYCResultsCache] ' . $msg, compact('transactionId'));

            return CommandAlias::SUCCESS;
        }

        // Clear all
        if ($supportsTags) {
            Cache::tags([$this->tag])->flush();
            $this->info('✅ KYC cache (tagged) flushed successfully.');
            Log::info('[ClearKYCResultsCache] Tagged cache flushed.');
        } else {
            $keys = Cache::get($this->manualRegistryKey, []);
            foreach ($keys as $key) {
                Cache::forget($key);
            }

            Cache::forget($this->manualRegistryKey);
            $this->info("✅ Manually tracked KYC result keys cleared (" . count($keys) . " keys).");
            Log::info('[ClearKYCResultsCache] Manual keys cleared.', ['count' => count($keys)]);
        }

        return CommandAlias::SUCCESS;
    }

    protected function supportsCacheTags(): bool
    {
        $store = Cache::getStore();

        return method_exists($store, 'tags') &&
            in_array(class_basename($store), ['RedisStore', 'MemcachedStore']);
    }
}
