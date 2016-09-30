<?php

namespace Ezcache\Cache;

class Cache {

    const CACHE_FILE      = "file";
    const CACHE_REDIS     = "redis";
    const CACHE_MEMCACHED = "memcached";

    public static function create(string $cacheType = CACHE_METHOD, $cacheGroup = null) : CacheInterface {
        switch ($cacheType) {
            case self::CACHE_MEMCACHED:
                return new MemCached($cacheGroup);
                break;

            case self::CACHE_REDIS:
                return new Redis($cacheGroup);
                break;
            case self::CACHE_FILE:
            default:
                return new FileCache($cacheGroup);
                break;
        }
    }
}