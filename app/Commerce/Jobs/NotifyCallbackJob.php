<?php

namespace App\Commerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotifyCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $url;
    public array $payload;

    public function __construct(string $url, array $payload)
    {
        $this->url = $url;
        $this->payload = $payload;
    }

    public function handle(): void
    {
        try {
//            Http::asJson()->post($this->url, $this->payload);
//            Log::info('[NotifyCallbackJob] Callback successful', [
//                'url' => $this->url,
//                'payload' => $this->payload,
//            ]);
            $response = Http::post($this->url, $this->payload);
            Log::info('[NotifyCallback] Response', [
                'url' => $this->url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[NotifyCallbackJob] Callback failed', [
                'url' => $this->url,
                'payload' => $this->payload,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
