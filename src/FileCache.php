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

    const JSON_FORMAT  = '.json';

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
    public function setNamespace(string $namespace) {
        $this->namespace = trim($namespace, '//, ');
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

        try {

            if (!$file = fopen($filePath, 'w')) {
                throw new CacheException("Failed to open the file $filePath for writing.");
            }

            // Uses the ttl passed in the function call or uses the default value
            $ttl      = $ttl ?? $this->ttl;
            $interval = new DateInterval(empty($ttl) ? 'P100Y' : "PT{$ttl}S");
            $date     = new DateTime();

            $fileData = [
                'value'      => serialize($value),
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

        if (empty($fileData) || ($isValid && date('Y-m-d H:i:s') > $fileData['expires_at'])) {
            return false;
        }

        return true;
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
     * Clear the cache directory.
     *
     * @param string|null $namespace the cache namespace.
     *
     * @return bool true on success or false on failure.
     */
    public function clear(string $namespace = null) : bool {
        //todo implement this.
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

        if (!((file_exists($cacheDirectory) && is_writable($cacheDirectory)) || mkdir($cacheDirectory, 0755, true))) {
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
     * Return the path where the file should be located.
     *
     * @param string $key the cache key
     *
     * @return string the file path
     */
    private function getFilePath(string $key) : string {
        $ds = DIRECTORY_SEPARATOR;
        return $this->cacheDirectory . (!empty($this->namespace) ? $this->namespace . $ds : '') . $key . self::JSON_FORMAT;
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
        return !$contents ? [] : json_decode($contents, true);
    }
}