<?php

namespace Bahiash\Omie\Tests\Stubs;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;

/**
 * Cache fake que implementa Repository e suporta lock() para testes do OmieRateLimiter.
 */
class FakeCacheWithLock implements Repository
{
    protected array $data = [];

    public function __construct(
        protected Lock $lock,
        protected $getCallback = null,
        protected $putCallback = null
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->getCallback !== null) {
            return ($this->getCallback)($key, $default);
        }
        return $this->data[$key] ?? $default;
    }

    public function put($key, $value, $ttl = null): bool
    {
        if ($this->putCallback !== null) {
            return ($this->putCallback)($key, $value, $ttl);
        }
        $this->data[$key] = $value;
        return true;
    }

    public function forever($key, $value): bool
    {
        return $this->put($key, $value, null);
    }

    public function sear($key, \Closure $callback)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        $value = $callback();
        $this->forever($key, $value);
        return $value;
    }

    public function lock($name, $seconds = 0): Lock
    {
        return $this->lock;
    }

    public function has($key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function forget($key): bool
    {
        unset($this->data[$key]);
        return true;
    }

    public function flush(): bool
    {
        $this->data = [];
        return true;
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        return $this->put($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->forget($key);
    }

    public function clear(): bool
    {
        return $this->flush();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        return $this->putMany($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function getStore(): Store
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function many(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    public function putMany(iterable $values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }
        return true;
    }

    public function increment($key, $value = 1): int|bool
    {
        $current = (int) $this->get($key, 0);
        $this->put($key, $current + $value);
        return $current + $value;
    }

    public function decrement($key, $value = 1): int|bool
    {
        $current = (int) $this->get($key, 0);
        $this->put($key, $current - $value);
        return $current - $value;
    }

    public function add($key, $value, $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }
        return $this->put($key, $value, $ttl);
    }

    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function remember($key, $ttl, \Closure $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    public function rememberForever($key, \Closure $callback)
    {
        return $this->remember($key, null, $callback);
    }
}
