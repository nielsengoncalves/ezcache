<?php

class PHPUnitUtils extends {

    /**
     * getPrivateProperty
     *
     * @param 	string $className
     * @param 	string $propertyName
     *
     * @return	ReflectionProperty
     */
    public function getPrivateProperty($className, $propertyName) : ReflectionProperty {
        $reflector = new ReflectionClass($className);
        $property = $reflector->getProperty($propertyName);
        $property->setAccessible(true);
        return $property;
    }
}