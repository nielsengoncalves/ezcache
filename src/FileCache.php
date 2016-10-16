<?php

namespace Ezcache\Cache;

use DateInterval;
use DateTime;
use Exception;

/**
 * Class FileCache
 *
 * @package Ezcache\Cache
 * @author nielsengoncalves
 */
class FileCache implements CacheInterface {

    use CacheUtilsTrait;

    const JSON_FORMAT = '.json';

    private $cacheDirectory;
    private $ttl;
    private $lastError = [];
    private $namespace;

    /**
     * FileCache constructor.
     *
     * @param string $directory the directory where cache operations will happen.
     * @param int $ttl the cache life time in seconds (0 = Forever).
     * @param string $namespace the cache namespace.
     */
    public function __construct(string $directory, int $ttl = 0, string $namespace = null) {
        $this->setCacheDirectory($directory);
        $this->ttl = $ttl;
        if ($namespace !== null) {
            $this->setNamespace($namespace);
        }
    }

    /**
     * Set the cache namespace.
     *
     * @param string $namespace the cache namespace.
     */
    public function setNamespace(string $namespace) : bool {
        $namespace = trim($namespace, '//, ');
        $dir = $this->getBasePath($namespace);

        if ((is_dir($dir) && is_writable($dir)) || @mkdir($dir, 0755)) {
            $this->namespace = $namespace;
            return true;
        }
        $this->setLastError(new CacheException("The $dir is not writable or it was not possible to create the directory."));
        return false;
    }

    /**
     * Set a value to a key on cache.
     *
     * @param string $key the key to be setted.
     * @param mixed $value the correspondent value of that cache key.
     * @param int|null $ttl the cache life time in seconds (If no value passed will use the default value).
     *
     * @return bool true on success or false on failure.
     */
    public function set(string $key, $value, int $ttl = null) : bool {

        $filePath = $this->getFilePath($key);

        // Uses the ttl passed in the function call or uses the default value
        $ttl      = $ttl ?? $this->ttl;
        $interval = new DateInterval(empty($ttl) ? 'P100Y' : "PT{$ttl}S");
        $date     = new DateTime();

        $fileData = json_encode([
            'value'      => serialize($value),
            'created_at' => $date->format('Y-m-d H:i:s'),
            'expires_at' => $date->add($interval)->format('Y-m-d H:i:s')
        ]);

        return file_put_contents($filePath, $fileData);
    }

    /**
     * Return the valid cache value stored with the given key.
     *
     * @param string $key the cache key to be found.
     *
     * @return mixed the data found.
     */
    public function get(string $key) {

        $fileData = $this->getFileData($key);

        if (empty($fileData) || (date('Y-m-d H:i:s') > $fileData['expires_at'])) {
            return null;
        }

        return unserialize($fileData['value']);
    }

    /**
     * Delete cache especified by key.
     *
     * @param string $key the cache key to be deleted.
     *
     * @return bool true on success or false on failure.
     */
    public function delete(string $key) : bool {
        return unlink($this->getFilePath($key));
    }

    /**
     * Check if given key exists and is valid on cache.
     *
     * @param string $key the cache key to be verified.
     * @param bool $isValid if set to true the function will verify if it is valid (not expired).
     *
     * @return bool true if exists false otherwise.
     */
    public function exists(string $key, bool $isValid = false) : bool {
        $fileData = $this->getFileData($key);
        return !(empty($fileData) || ($isValid && date('Y-m-d H:i:s') > $fileData['expires_at']));
    }

    /**
     * Renew the cache expiration time.
     *
     * @param string $key the cache key to be renewed.
     * @param int|null $ttl extra time to live in seconds.
     *
     * @return bool true on success or false on failure.
     */
    public function renew(string $key, int $ttl = null) : bool {

        $filePath = $this->getFilePath($key);
        $fileData = $this->getFileData($key);

        if (empty($fileData)) {
            return false;
        }

        $ttl = $ttl ?? $this->ttl;
        $interval = new DateInterval(empty($ttl) ? 'P100Y' : "PT{$ttl}S");
        $fileData['expires_at'] = (new DateTime())->add($interval)->format('Y-m-d H:i:s');
        file_put_contents($filePath, json_encode($fileData));
        return true;
    }

    /**
     * Clear all cache files at directory.
     *
     * @param string|null $namespace the cache namespace.
     *
     * @return bool true on success or false on failure.
     */
    public function clear(string $namespace = null) : bool {

        $dir = $this->getBasePath($namespace);
        $files = $this->streamSafeGlob($dir, '*.cache' . self::JSON_FORMAT);
        foreach ($files as $file) {
            if (is_file($file)) {
                if (!unlink($file)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Set the directory where cache operations will happen.
     *
     * @param string $cacheDirectory the directory where cache operations will happen.
     *
     * @return bool true on success or false on failure.
     */
    public function setCacheDirectory(string $cacheDirectory) : bool {
        $this->cacheDirectory = null;
        $cacheDirectory = rtrim($cacheDirectory, '//, ') . '/';
        if (!((file_exists($cacheDirectory) && is_writable($cacheDirectory)) || @mkdir($cacheDirectory, 0755, true))) {
            $this->setLastError(new Exception("Failed to use $cacheDirectory as cache directory."));
            return false;
        }
        $this->cacheDirectory = $cacheDirectory;
        return true;
    }

    /**
     * Return the last error occurred.
     *
     * @return array the array with the last error data.
     */
    public function getLastError() : array {
        return $this->lastError;
    }

    /**
     * Return the path where the cache file should be located.
     *
     * @param string $key the cache key
     *
     * @return string the file path
     */
    private function getFilePath(string $key) : string {
        return $this->getBasePath($this->namespace) . $key . ".cache" . self::JSON_FORMAT;
    }

    /**
     * Return the base path where the cache files should be located.
     *
     * @return string the file path
     */
    private function getBasePath(string $namespace = null) : string {
        return $this->cacheDirectory . (!empty($namespace) ? $namespace . DIRECTORY_SEPARATOR : '');
    }

    /**
     * Set the last error that ocurred using the lib
     *
     * @param Exception $ex the exception
     */
    private function setLastError(Exception $ex) {
        $this->lastError['code']    = $ex->getCode();
        $this->lastError['message'] = $ex->getMessage();
        $this->lastError['trace']   = $ex->getTraceAsString();
    }

    /**
     * Get the file data
     *
     * @param string $key the cache key
     *
     * @return array with the file data or empty array when no data found
     */
    private function getFileData(string $key) : array {
        $filePath = $this->getFilePath($key);
        $contents = @file_get_contents($filePath);

        if (!$contents) {
            return [];
        } else if (($data = json_decode($contents, true)) === null && json_last_error() != JSON_ERROR_NONE) {
            $this->setLastError(new CacheException(sprintf("Failed to decode the %s data.", $key)));
            return [];
        }

        return $data;
    }
}