<?php

namespace EzcacheTest\Cache;

use Ezcache\Cache\FileCache;
use org\bovigo\vfs\vfsStream;
use PHPUnit_Framework_TestCase;
use stdClass;

class FileCacheTest extends PHPUnit_Framework_TestCase {

    use PHPUnitUtilsTrait;

    private $file;
    private $getFileData;

    public function setUp() {
        vfsStream::setup();
        $this->getFileData = $this->getPrivateMethod('Ezcache\Cache\FileCache', 'getFileData');
        $this->file = new FileCache(vfsStream::url('cache'));
    }

    public function tearDown() {

    }

    /**
     * Tests setNamespace method
     */
    public function testSetNamespace() {

        $file = new FileCache(vfsStream::url('cache'), 0, 'namespacename1');
        $property = $this->getPrivateProperty('Ezcache\Cache\FileCache', 'namespace');

        // Checks if constructor namespace is working
        $this->assertEquals($property->getValue($file), 'namespacename1', 'Asserting that passing namespace on constructor works.');

        // Checks if the setNamespace method is working
        $file->setNamespace('namespacename2');
        $this->assertEquals($property->getValue($file), 'namespacename2', 'Asserting that setNamespace method works.');
    }

    /**
     * Tests if set expiration is working
     */
    public function testSetExpiration() {
        $this->withExpirationTest();
        $this->withNoExpirationTest();
    }

    /**
     * Tests setting and getting all data types supported by PSR-6
     */
    public function testSetGetAllTypes() {
        $this->typeTest('string',  'abcd');
        $this->typeTest('integer', 999999);
        $this->typeTest('double',  12.223);
        $this->typeTest('boolean', false);
        $this->typeTest('null',    null);
        $this->typeTest('array',   ['key1' => 1, 'key2' => 2]);
        $this->typeTest('object',  new stdClass());
    }

    /**
     * Tests deleting files from cache
     */
    public function testDelete() {
        $toBeDeleted = 'FileToBeDeleted';
        $this->file->set($toBeDeleted, 10);
        $data = $this->file->get($toBeDeleted);
        $this->assertEquals(10, $data, 'Asserting that file exists.');

        $this->file->delete($toBeDeleted);
        $data = $this->file->get($toBeDeleted);
        $this->assertEquals(null, $data, 'Asserting that file was deleted.');
    }

    /**
     * Tests no expiration cache
     */
    private function withNoExpirationTest() {
        $noExpirationInt = 'NoExpiration';
        $this->file->set($noExpirationInt, 10);
        $fileData = $this->getFileData->invokeArgs($this->file, [$noExpirationInt]);
        $this->assertGreaterThanOrEqual(3153600000, strtotime($fileData["expires_at"]) - strtotime($fileData["created_at"]), 'Asserting that cache expiration is bigger or equals a year with default value.');

        $this->file->set($noExpirationInt, 99, 0);
        $fileData = $this->getFileData->invokeArgs($this->file, [$noExpirationInt]);
        $this->assertGreaterThanOrEqual(3153600000, strtotime($fileData["expires_at"]) - strtotime($fileData["created_at"]), 'Asserting that cache expiration is bigger or equals a year forcing no expiration.');
    }

    /**
     * Tests cache with expiration time
     */
    private function withExpirationTest() {
        $oneMinuteExp = 'OneMinuteExpiration';
        $this->file->set($oneMinuteExp, 'val1', 60);
        $fileData = $this->getFileData->invokeArgs($this->file, [$oneMinuteExp]);
        $this->assertEquals(60, strtotime($fileData["expires_at"]) - strtotime($fileData["created_at"]), 'Asserting that cache expiration is one minute.');
    }

    /**
     * Sets a value to cache, and verifies if the get method gets the correct type
     *
     * @param string $type the type being checked
     * @param mixed $value the value of given type
     */
    private function typeTest(string $type, $value) {
        $fileName = ucfirst($type) . 'Type';
        $this->file->set($fileName, $value);
        $data = $this->file->get($fileName);
        $this->assertEquals($value, $data);
        $this->assertInternalType($type, $data, 'Asserting that ' . $type . ' was stored correctly.');
    }
}
