<?php

namespace EzcacheTest\Cache;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

trait PHPUnitUtilsTrait
{
    /**
     * Get private property from a class.
     *
     * @param string $className    the class name
     * @param string $propertyName the property name
     *
     * @return ReflectionProperty
     */
    public function getPrivateProperty($className, $propertyName) : ReflectionProperty
    {
        $property = (new ReflectionClass($className))->getProperty($propertyName);
        $property->setAccessible(true);

        return $property;
    }

    /**
     * Get private method from a class.
     *
     * @param string $className  the class name
     * @param string $methodName the method name
     *
     * @return ReflectionMethod
     */
    public function getPrivateMethod($className, $methodName) : ReflectionMethod
    {
        $method = (new ReflectionClass($className))->getMethod($methodName);
        $method->setAccessible(true);

        return $method;
    }
}
