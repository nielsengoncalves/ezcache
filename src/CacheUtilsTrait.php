<?php

namespace Ezcache\Cache;

/**
 * Trait CacheUtilsTrait
 */
trait CacheUtilsTrait {

    /**
     * Glob that is safe with streams (vfs for example)
     *
     * @param string $directory the directory
     * @param string $filePattern the file pattern
     *
     * @return array containing match files
     */
    public function streamSafeGlob($directory, $filePattern) : array {
        $files = scandir($directory);
        $found = [];

        foreach ($files as $filename) {
            if (in_array($filename, ['.', '..'])) {
                continue;
            }

            if (fnmatch($filePattern, $filename)) {
                $found[] = "{$directory}/{$filename}";
            }
        }

        return $found;
    }
}