<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

use PeskyORM\ORM\TableStructure\TableColumn\ValueToObjectConverter\ValueToObjectConverterInterface;

trait CanConvertValueToClassInstance
{
    protected ?string $classNameForValueToObjectFormatter = null;

    /**
     * @return string|null|ValueToObjectConverterInterface
     * @noinspection PhpDocSignatureInspection
     */
    public function getClassNameForValueToClassInstanceConverter(): ?string
    {
        return $this->classNameForValueToObjectFormatter;
    }

    /**
     * Used in 'object' formatter for columns with JSON values and also can be used in custom formatters
     * @param string|null $className - string: custom class name | null: \stdClass
     * Custom class name must implement PeskyORM\ORM\TableStructureOld\TableColumn\ValueToObjectConverter\ValueToObjectConverterInterface or extend PeskyORM\ORM\TableStructureOld\TableColumn\ValueToObjectConverter\ValueToObjectConverter class
     * Note: you can use PeskyORM\ORM\TableStructureOld\TableColumn\ValueToObjectConverter\ConvertsArrayToObject trait for simple situations
     * @see
     */
    public function setClassNameForValueToClassInstanceConverter(?string $className): static
    {
        if (
            $className
            && (
                !class_exists($className)
                || !(new \ReflectionClass($className))->implementsInterface(ValueToObjectConverterInterface::class)
            )
        ) {
            throw new \InvalidArgumentException(
                '$className argument must be a string and contain a full name of a class that implements ' . ValueToObjectConverterInterface::class
            );
        }
        $this->classNameForValueToObjectFormatter = $className;
        return $this;
    }
}