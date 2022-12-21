<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\EmailColumnInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrimaryKey;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrivate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CannotHaveDefaultValue;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;
use PeskyORM\Utils\ValueTypeValidators;

/**
 * Note: column will trim and lowercase values and
 * empty strings will be converted to nulls.
 * This way it is easier to use emails for authentication
 * and make uniqueness constraint work better.
 */
class EmailColumn extends RealTableColumnAbstract implements
    EmailColumnInterface,
    UniqueTableColumnInterface
{
    use CanBeNullable;
    use CanBeUnique;
    use CanBePrivate;
    use CanBePrimaryKey;
    use CannotHaveDefaultValue;

    public function __construct(string $name = 'email')
    {
        parent::__construct($name);
    }

    public function getDataType(): string
    {
        return TableColumnDataType::STRING;
    }

    final public function isAutoUpdatingValues(): bool
    {
        return false;
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = $value->__toString();
        }
        if (is_string($value)) {
            return $this->normalizeEmail($value, $isFromDb);
        }
        return $value;
    }

    public function normalizeEmail(string $value, bool $isFromDb): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return mb_strtolower($value);
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isEmail($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_EMAIL
                ),
            ];
        }
        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): string {
        return (string)$validatedValue;
    }
}