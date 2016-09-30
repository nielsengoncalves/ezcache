<?php

namespace Ezcache\Cache;

interface CacheInterface {

    public function get(string $key) : array;

    public function set(string $key, $value, int $ttl = null) : bool;

    public function delete(string $key) : bool;

    public function exists(string $key) : bool;

    public function renew(string $key, int $ttl) : bool;

    public function clear(string $cacheDirectory) : bool;

}