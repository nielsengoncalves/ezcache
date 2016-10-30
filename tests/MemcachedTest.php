<?php

namespace EzcacheTest\Cache;

use Ezcache\Cache\MemCached;
use PHPUnit_Framework_TestCase;

class MemcachedTest extends PHPUnit_Framework_TestCase
{
    /** @var mixed */
    private $memcached;
    public function setUp() {
        $this->memcached = $this->getMockBuilder('Memcached')->getMock();
    }

    public function testConstruct() {
        new MemCached($this->memcached, 0, 'testNS');
    }

    public function testSet() {
        $this->memcached->method('set')
            ->will($this->onConsecutiveCalls(true, true));

        $memcached = new MemCached($this->memcached, 0, 'testNS');

        $testSet1 = $memcached->set('ANY_KEY', []);
        $this->assertTrue($testSet1);

        $testSet2 = $memcached->set('ANY_KEY', [], 2);
        $this->assertTrue($testSet2);
    }

}