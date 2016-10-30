<?php

namespace Ezcache\Cache;

/**
 * @codeCoverageIgnore
 * @author: William Johnson dos Santos Okano <williamokano@gmail.com>
 */
class MemCached implements CacheInterface
{
    /** @var string */
    private $namespace;

    /** @var int */
    private $ttl;

    /** @var \Memcached */
    private $memcachedInstance;

    /**
     * MemCached constructor.
     *
     * @param \Memcached $memcached the memcached instance
     * @param int        $ttl       the cache lifetime in seconds (0 = Forever)
     * @param string     $namespace the cache namespace
     */
    public function __construct(\Memcached $memcached, $ttl = 0, string $namespace = null)
    {
        $this->ttl = $ttl;
        $this->memcachedInstance = $memcached;
        if ($namespace !== null) {
            $this->setNamespace($namespace);
        }
    }

    /**
     * Set the cache namespace.
     *
     * @param string $namespace the cache namespace
     *
     * @return bool true on success or false on failure
     */
    public function setNamespace(string $namespace) : bool
    {
        $this->namespace = $namespace;

        return true;
    }

    /**
     * Set a value to a key on cache.
     *
     * @param string   $key   the key to be set.
     * @param mixed    $value the correspondent value of that cache key.
     * @param int|null $ttl   the cache life time in seconds (If no value passed will use the default value).
     *
     * @return bool true on success or false on failure.
     */
    public function set(string $key, $value, int $ttl = null) : bool
    {
        $ttl = $ttl ?? $this->ttl;
        $key = $this->namespacedKey($key);

        return $this->memcachedInstance->set($key, $value, $ttl);
    }

    /**
     * Return the valid cache value stored with the given key.
     *
     * @param string $key the cache key to be found.
     *
     * @return mixed the data found.
     */
    public function get(string $key)
    {
        $key = $this->namespacedKey($key);
        $value = $this->memcachedInstance->get($key);
        if ($this->exists($key)) {
            return $value;
        }
    }

    /**
     * Delete cache especified by key.
     *
     * @param string $key the cache key to be deleted.
     *
     * @return bool true on success or false on failure.
     */
    public function delete(string $key) : bool
    {
        $key = $this->namespacedKey($key);

        return $this->memcachedInstance->delete($key);
    }

    /**
     * Check if given key exists and is valid on cache.
     *
     * @param string $key     the cache key to be verified.
     * @param bool   $isValid if set to true the function will verify if it is valid (not expired).
     *
     * @return bool true if exists false otherwise.
     */
    public function exists(string $key, bool $isValid = false) : bool
    {
        $this->memcachedInstance->get($key);

        return $this->memcachedInstance->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    /**
     * Renew the cache expiration time.
     *
     * @param string   $key the cache key to be renewed.
     * @param int|null $ttl extra time to live in seconds.
     *
     * @return bool true on success or false on failure.
     */
    public function renew(string $key, int $ttl) : bool
    {
        $key = $this->namespacedKey($key);
        $ttl = $ttl ?? $this->ttl;
        if ($this->exists($key)) {
            return $this->memcachedInstance->touch($key, $ttl);
        }
        return false;
    }

    /**
     * Clear all cache records.If namespace set, just clear those that starts with the namespace.
     *
     * @param string|null $namespace the cache namespace.
     *
     * @return bool true on success or false on failure.
     */
    public function clear(string $namespace = null) : bool
    {
        $namespace = $namespace ?? $this->namespace;
        if ($namespace === null) {
            return $this->memcachedInstance->flush();
        }

        // Find all keys, iterate and them delete the only ones that starts with the namespace
        $cacheKeys = $this->memcachedInstance->getAllKeys();
        $filteredCacheKeys = array_filter($cacheKeys, function ($key) use ($namespace) {
            return strpos($key, $namespace) === 0;
        });

        array_walk($filteredCacheKeys, [$this, 'delete']);

        return true;
    }

    /**
     * Return the key with the namespace. Since Memcached doesn't support namespaces
     * the method just simulate it by pre-pending some fixed string to the key.
     *
     * @param string $key the key to prepend the namespace, if necessary
     *
     * @return string the "namespaced" key
     */
    private function namespacedKey(string $key) : string
    {
        if ($this->namespace !== null) {
            return $this->namespace.$key;
        }

        return $key;
    }
}
