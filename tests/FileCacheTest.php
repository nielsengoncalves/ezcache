<?php

namespace cache;

use phpDocumentor\Reflection\Types\Self_;
use PHPUnit_Framework_TestCase;

class FileCacheTest extends PHPUnit_Framework_TestCase {

    const DIR = '%tmp%/ezcache-test/cache';

//    public function testGetNotExistingOnCache() {
//        $fileCache = new FileCache(self::DIR);
//        $data = $fileCache->get('tst1');
//        $this->assertEquals(["is_hit" => false, "value" => null], $data);
//        rmdir(DIR);
//    }
//
//    public function testGetExisting() {
//
//    }

}
