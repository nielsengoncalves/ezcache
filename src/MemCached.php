<?php

namespace Ezcache\Cache;

class MemCached implements CacheInterface {

    public function setNamespace(string $namespace) {

    }

    public function get(string $key) : array {

    }

    public function set(string $key, $value, int $ttl = null) : bool {

    }

    public function delete(string $key) : bool {

    }

    public function exists(string $key, bool $isValid = false) : bool {

    }

    public function renew(string $key, int $ttl) : bool {

    }

    public function clear(string $namespace = null) : bool {

    }
}