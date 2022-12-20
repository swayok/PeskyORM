<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest;

use ArrayAccess;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class BaseTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        TestingApp::resetServiceContainer();
    }

    public static function tearDownAfterClass(): void
    {
        TestingApp::resetServiceContainer();
    }

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

    protected function fillAdminsTable(int $limit = 0): array
    {
        return TestingApp::fillAdminsTable(
            TestingAdminsTable::getInstance()->getConnection(true),
            $limit
        );
    }
}