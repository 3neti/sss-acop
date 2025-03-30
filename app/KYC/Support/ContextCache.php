<?php

namespace App\KYC\Support;

use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Contracts\Cache\Repository;
use Closure;

class ContextCache
{
    protected string $prefix;
    protected ?string $tag = null;
    protected ?int $ttl = null;

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    public function tag(string $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    public function ttl(int $minutes): static
    {
        $this->ttl = $minutes;
        return $this;
    }

    public function remember(string $key, Closure $callback)
    {
        $cacheKey = "{$this->prefix}:{$key}";
        $store = $this->getStore();

        if ($store->has($cacheKey)) {
            Log::debug("[context()] Cache hit", ['key' => $cacheKey]);
            return $store->get($cacheKey);
        }

        Log::info("[context()] Cache miss", ['key' => $cacheKey]);

        $data = $callback();
        $store->put($cacheKey, $data, now()->addMinutes($this->ttl ?? 30));
        $this->trackManualKey($cacheKey);

        return $data;
    }

    public function clear(string $key): void
    {
        $cacheKey = "{$this->prefix}:{$key}";
        $store = $this->getStore();

        $deleted = $store->forget($cacheKey);
        Log::info("[context()] Cleared single key", ['key' => $cacheKey, 'deleted' => $deleted]);
    }

    public function flush(): bool
    {
        $store = $this->getStore();

        if ($this->tag && $this->supportsTags()) {
            $store->flush();
            Log::info("[context()] Flushed cache for tag '{$this->tag}'");
            return true;
        }

        // Fallback: manual registry key flush
        $registryKey = "{$this->prefix}_keys";
        $keys = Cache::get($registryKey, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget($registryKey);

        Log::warning("[context()] Tagging not supported — flushed manually tracked keys.", [
            'registryKey' => $registryKey,
            'count' => count($keys),
        ]);

        return true;
    }

    protected function getStore(): Repository
    {
        if ($this->tag && $this->supportsTags()) {
            return Cache::tags([$this->tag]);
        }

        return Cache::store();
    }

    protected function supportsTags(): bool
    {
        $store = Cache::getStore();

        return method_exists($store, 'tags') &&
            in_array(class_basename($store), ['RedisStore', 'MemcachedStore']);
    }

    protected function trackManualKey(string $key): void
    {
        if ($this->tag && $this->supportsTags()) {
            return; // No need to track when tagging is available
        }

        $registryKey = "{$this->prefix}_keys";
        $keys = Cache::get($registryKey, []);

        if (!in_array($key, $keys)) {
            $keys[] = $key;
            Cache::forever($registryKey, $keys);
        }
    }
}
