<?php

namespace PeskyORM\Tests\PeskyORMTest;

use ArrayAccess;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Swayok\Utils\ReflectionUtils;

class BaseTestCase extends TestCase
{
    /**
     * Asserts that an array has a specified subset.
     *
     * @param array|ArrayAccess $subset
     * @param array|ArrayAccess $array
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     * @throws ExpectationFailedException
     *
     * @codeCoverageIgnore
     */
    public static function assertArraySubset($subset, $array, bool $checkForObjectIdentity = false, string $message = ''): void
    {
        if (!(is_array($subset) || $subset instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                1,
                'array or ArrayAccess'
            );
        }
        
        if (!(is_array($array) || $array instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                2,
                'array or ArrayAccess'
            );
        }
        
        $constraint = new ArraySubset($subset, $checkForObjectIdentity);
        
        static::assertThat($array, $constraint, $message);
    }
    
    protected function getObjectPropertyValue($object, string $propertyName)
    {
        return ReflectionUtils::getObjectPropertyValue($object, $propertyName);
    }
    
    protected function callObjectMethod($object, string $methodName, ...$args)
    {
        return ReflectionUtils::callObjectMethod($object, $methodName, ...$args);
    }
    
    protected function getMethodReflection($object, string $methodName): ReflectionMethod
    {
        return ReflectionUtils::getMethodReflection($object, $methodName);
    }
    
}