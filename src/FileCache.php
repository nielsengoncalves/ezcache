<?php

namespace Ezcache\Cache;

use DateInterval;
use DateTime;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use RecursiveIteratorIterator;
use Exception;

class FileCache implements CacheInterface {

    const JSON_FORMAT  = '.json';
    const TXT_FORMAT   = '.txt';
    const CACHE_FORMAT = '.cache';

    private $cacheDirectory;
    private $ttl;
    private $lastError = [];

    /**
     * FileCache constructor.
     *
     * @param string $directory the directory where cache will be stored/retrieved
     * @param int $ttl cache time to live in seconds
     */
    public function __construct(string $directory, int $ttl = 0) {
        $this->setCacheDirectory($directory);
        $this->ttl = $ttl;
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * @param string $key the cache key
     *
     * @return mixed the data found on cache
     */
    public function get(string $key) : array {

        $filePath = $this->getFilePath($key);
        $fileData = json_decode(file_get_contents($filePath), true);

        if (empty($fileData) || (date('Y-m-d H:i:s') > $fileData['expires_at'])) {
            return ['is_hit' => false, 'value' => null];
        }

        return ['is_hit' => true, 'value' => json_decode($fileData)['value']];
    }

    /**
     * Set the value identified by key to cache
     *
     * @param string $key the key
     * @param mixed $value the value
     * @param int|null $ttl time to live in seconds
     *
     * @return bool true on succes or false on failure
     */
    public function set(string $key, $value, int $ttl = null) : bool {

        $filePath = $this->getFilePath($key);

        try {

            $file = fopen($filePath, 'w');
            if (!$file) {
                throw new CacheException(sprintf("Failed to open the file %s for writing.", $filePath));
            }

            // Uses the ttl passed on function call or uses the default value
            $ttl = $ttl ?? $this->ttl;

            if (empty($ttl)) {
                $interval = new DateInterval("P100Y");
            } else {
                $interval = new DateInterval("PT{$ttl}S");
            }

            $date = new DateTime();

            $fileData = [
                'value'      => $value,
                'created_at' => $date->format('Y-m-d H:i:s'),
                'expires_at' => $date->add($interval)->format('Y-m-d H:i:s')
            ];

            fwrite($file, json_encode($fileData));
            fclose($file);
            return true;
        } catch (Exception $exc) {
            $this->setLastError($exc);
            return false;
        }
    }

    /**
     * Delete cache especified by key
     *
     * @param string $key the cache key to be deleted
     *
     * @return bool true on success or false on failure
     */
    public function delete(string $key) : bool {
        return unlink($this->getFilePath($key));
    }

    /**
     * Check if given key exists and is valid on cache
     *
     * @param string $key the key to be verified
     *
     * @return bool true if exists false otherwise
     */
    public function exists(string $key) : bool {
        return $this->get($key)["is_hit"];
    }

    public function renew(string $key, int $ttl = null) : bool {

        $filePath = $this->getFilePath($key);
        $fileData = json_decode(file_get_contents($filePath), true);

        if (empty($fileData)) {
            return false;
        }

        if (empty($ttl = $ttl ?? $this->ttl)) {
            $interval = new DateInterval("P100Y");
        } else {
            $interval = new DateInterval("PT{$ttl}S");
        }

        $fileData["expires_at"] = (new DateTime())->add($interval)->format("Y-m-d H:i:s");
        file_put_contents($filePath, json_encode($fileData));
        return true;
    }

    /**
     * Clear the cache directory
     *
     * @param string|null $cacheDirectory
     * @return bool
     */
    public function clear(string $cacheDirectory) : bool {

        $directory = $cacheDirectory ?? $this->cacheDirectory;

        try {
            $rdi = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
            $ri  = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($ri as $file) {
                is_dir($file) ?  $this->clear($file) : unlink($file);
            }

        } catch (Exception $exc) {
            $this->setLastError($exc);
            return false;
        }

        return true;
    }

    /**
     * Set the directory where cache will be stored or retrieved.
     *
     * @param string $cacheDirectory the directory where cache will be stored or retrieved
     *
     * @return bool true on success or false on failure
     */
    public function setCacheDirectory(string $cacheDirectory) : bool {
        $this->cacheDirectory = null;
        if (!file_exists($cacheDirectory)) {
            if (!$mkdir = mkdir($cacheDirectory, 0755, true) && $this->cacheDirectory = $cacheDirectory) {
                $this->setLastError(new CacheException("Failed to create the directory {$cacheDirectory}."));
                return false;
            }
        } else if (!is_writable($cacheDirectory)) {
            $this->setLastError(new CacheException("Not enough writing permissions to the directory {$cacheDirectory}."));
            return false;
        }
        $this->cacheDirectory = $cacheDirectory;

        return true;
    }

    /**
     * Return the last error that ocurred using the lib
     *
     * @return array the array with the last error data
     */
    public function getLastError() : array {
        return $this->lastError;
    }

    /**
     * Return the path where the file should be located.
     *
     * @param string $key the cache key
     *
     * @return string the file path
     */
    private function getFilePath(string $key) : string {
        return DIRECTORY_SEPARATOR . trim($this->cacheDirectory, '//, ') . DIRECTORY_SEPARATOR . $key . self::JSON_FORMAT;
    }

    /**
     * Set the last error that ocurred using the lib
     *
     * @param Exception $ex the exception
     */
    private function setLastError(Exception $ex) {
        $this->lastError["code"]    = $ex->getCode();
        $this->lastError["message"] = $ex->getMessage();
        $this->lastError["trace"]   = $ex->getTraceAsString();
    }
}