<?php

namespace EzcacheTest\Cache;

use Ezcache\Cache\FileCache;
use org\bovigo\vfs\vfsStream;
use PHPUnit_Framework_TestCase;
use stdClass;

class FileCacheTest extends PHPUnit_Framework_TestCase
{
    use PHPUnitUtilsTrait;

    /** @var FileCache */
    private $fileCache;

    /** @var \ReflectionMethod */
    private $getFileData;

    /** @var \org\bovigo\vfs\vfsStreamDirectory */
    private $vfsStream;
    private $dir;

    public function setUp()
    {
        $this->vfsStream = vfsStream::setup();
        $this->getFileData = $this->getPrivateMethod('Ezcache\Cache\FileCache', 'getFileData');
        $this->dir = $this->vfsStream->url().'/cache';
        $this->fileCache = new FileCache($this->dir);
    }

    public function tearDown()
    {
    }

    /**
     * Tests setNamespace method.
     */
    public function testSetNamespace()
    {
        $file = new FileCache($this->dir, 0, 'namespacename1');
        $property = $this->getPrivateProperty('Ezcache\Cache\FileCache', 'namespace');

        // Checks if constructor namespace is working
        $this->assertEquals($property->getValue($file), 'namespacename1', 'Asserting that passing namespace on constructor works.');

        // Checks if the setNamespace method is working
        $file->setNamespace('namespacename2');
        $this->assertEquals($property->getValue($file), 'namespacename2', 'Asserting that setNamespace method works.');
    }

    /**
     * Tests if set expiration is working.
     */
    public function testSetExpiration()
    {
        $this->withExpirationTest();
        $this->withNoExpirationTest();
    }

    /**
     * Tests setting and getting all data types supported by PSR-6.
     */
    public function testSetGetAllTypes()
    {
        $this->typeTest('string', 'abcd');
        $this->typeTest('integer', 999999);
        $this->typeTest('double', 12.223);
        $this->typeTest('boolean', false);
        $this->typeTest('null', null);
        $this->typeTest('array', ['key1' => 1, 'key2' => 2]);
        $this->typeTest('object', new stdClass());
    }

    /**
     * Tests deleting files from cache.
     */
    public function testDelete()
    {
        $toBeDeleted = 'FileToBeDeleted';
        $this->fileCache->set($toBeDeleted, 10);
        $data = $this->fileCache->get($toBeDeleted);
        $this->assertEquals(10, $data, 'Asserting that file exists.');

        $this->fileCache->delete($toBeDeleted);
        $data = $this->fileCache->get($toBeDeleted);
        $this->assertEquals(null, $data, 'Asserting that file was deleted.');
    }

    /**
     * Tests methods with expired key.
     */
    public function testExpiredCache()
    {
        $oneSecExp = 'OneSecExpirationCacheTest';
        $this->fileCache->set($oneSecExp, 'value', 1);

        //after this, the cache is expired
        sleep(2);
        $exists = $this->fileCache->exists($oneSecExp, true);
        $this->assertEquals(false, $exists, 'Asserting that key exists on cache and is not expired.');
        $exists = $this->fileCache->exists($oneSecExp);
        $this->assertEquals(true, $exists, 'Asserting that key exists on cache.');

        $data = $this->fileCache->get($oneSecExp);
        $this->assertEquals(null, $data, 'Asserting that data is empty because cache is expired.');

        $this->fileCache->renew($oneSecExp, 10);
        $data = $this->fileCache->get($oneSecExp);
        $this->assertEquals('value', $data, 'Asserting that the cache renew worked.');

        $renew = $this->fileCache->renew('Inexistent', 2);
        $this->assertFalse($renew, 'Asserting that is not possible to renew inexistent files.');
    }

    /**
     * Tests clearing file from cache.
     */
    public function testClear()
    {
        $this->fileCache->set('clear1', 100);
        $this->fileCache->set('clear2', 200);

        $get1 = $this->fileCache->get('clear1');
        $get2 = $this->fileCache->get('clear2');

        $this->assertTrue($get1 == 100 && $get2 == 200, 'Asserting that get1 and get2 were sucessefuly created on cache.');

        $clear = $this->fileCache->clear();

        $get1 = $this->fileCache->get('clear1');
        $get2 = $this->fileCache->get('clear2');

        $this->assertTrue($clear == true && [$get1, $get2] === [null, null], 'Asserting that all created cache files were cleared.');
    }

    public function testFail()
    {
        $invalidJson = '{"value":"s:5:\"21312\";","created_at":"2016-10-16 05:35:44",\'expires_at\':"2116-10-16 05:35:44"}';
        vfsStream::newFile('cache/invalid.cache.json')->withContent($invalidJson)->at($this->vfsStream);

        $data = $this->fileCache->get('invalid');
        $lastError = $this->fileCache->getLastError()['message'];
        $this->assertStringStartsWith('Failed to decode the', $lastError);

        vfsStream::newDirectory('cache/namespace3', 0000)
            ->at($this->vfsStream);
        $this->fileCache->setNamespace('namespace3');

        $this->assertStringEndsWith('is not writable or it was not possible to create the directory.', $this->fileCache->getLastError()['message']);
        $this->fileCache->setCacheDirectory($this->vfsStream->url().'/cache/namespace3');
        $lastError = $this->fileCache->getLastError()['message'];
        $this->assertStringStartsWith('Failed to use', $lastError);
        $this->assertStringEndsWith('as cache directory.', $lastError);
    }

    /**
     * Tests no expiration cache.
     */
    private function withNoExpirationTest()
    {
        $noExpirationInt = 'NoExpirationCacheTest';
        $this->fileCache->set($noExpirationInt, 10);
        $fileData = $this->getFileData->invokeArgs($this->fileCache, [$noExpirationInt]);
        $this->assertGreaterThanOrEqual(3153600000, strtotime($fileData['expires_at']) - strtotime($fileData['created_at']), 'Asserting that cache expiration is bigger or equals a year with default value.');

        $this->fileCache->set($noExpirationInt, 99, 0);
        $fileData = $this->getFileData->invokeArgs($this->fileCache, [$noExpirationInt]);
        $this->assertGreaterThanOrEqual(3153600000, strtotime($fileData['expires_at']) - strtotime($fileData['created_at']), 'Asserting that cache expiration is bigger or equals a year forcing no expiration.');
    }

    /**
     * Tests cache with expiration time.
     */
    private function withExpirationTest()
    {
        $oneMinuteExp = 'OneMinuteExpirationCacheTest';
        $this->fileCache->set($oneMinuteExp, 'val1', 60);
        $fileData = $this->getFileData->invokeArgs($this->fileCache, [$oneMinuteExp]);
        $this->assertEquals(60, strtotime($fileData['expires_at']) - strtotime($fileData['created_at']), 'Asserting that cache expiration is one minute.');
    }

    /**
     * Sets a value to cache, and verifies if the get method gets the correct type.
     *
     * @param string $type  the type being checked
     * @param mixed  $value the value of given type
     */
    private function typeTest(string $type, $value)
    {
        $fileName = ucfirst($type).'TypeCacheTest';
        $this->fileCache->set($fileName, $value);
        $data = $this->fileCache->get($fileName);
        $this->assertEquals($value, $data);
        $this->assertInternalType($type, $data, 'Asserting that '.$type.' was stored correctly.');
    }
}
