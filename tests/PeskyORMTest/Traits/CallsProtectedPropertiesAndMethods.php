<?php

namespace PeskyORM\Tests\PeskyORMTest\Traits;

use ReflectionClass;
use ReflectionMethod;

trait CallsProtectedPropertiesAndMethods
{
    
    protected function getObjectPropertyValue($object, string $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($propertyName);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
    
    protected function callObjectMethod($object, string $methodName, ...$args)
    {
        return $this->getMethodReflection($object, $methodName)
            ->invokeArgs($object, $args);
    }
    
    protected function getMethodReflection($object, string $methodName): ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}