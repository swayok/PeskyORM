<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Utils\ArgumentValidators;
use PeskyORM\Utils\ValueTypeValidators;

class IdColumn extends RealTableColumnAbstract
{
    public function __construct(string $name = 'id')
    {
        parent::__construct($name);
    }

    public function getDataType(): string
    {
        return TableColumnDataType::INT;
    }

    public function isNullableValues(): bool
    {
        return true;
    }

    public function isPrimaryKey(): bool
    {
        return true;
    }

    public function isAutoUpdatingValues(): bool
    {
        return false;
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_string($value) && trim($value) === '') {
            return null;
        }
        return $value;
    }

    protected function validateNormalizedValue(
        mixed $normalizedValue,
        bool $isFromDb,
        bool $isForCondition
    ): array {
        if (is_object($normalizedValue) && !($normalizedValue instanceof DbExpr)) {
            // PK value can be an object only if it is DbExpr instacne
            throw new \UnexpectedValueException(
                "Value for primary key column {$this->getNameForException()} can be an integer"
                . " or instance of " . DbExpr::class . '. '
                . ArgumentValidators::getValueInfoForException($normalizedValue)
            );
        }
        return parent::validateNormalizedValue($normalizedValue, $isFromDb, $isForCondition);
    }

    protected function validateIfNullValueIsAllowed(bool $isFromDb, bool $isForCondition): bool
    {
        return !$isFromDb || $isForCondition;
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isInteger($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_INTEGER
                ),
            ];
        }
        if (!$isForCondition && (int)$normalizedValue <= 0) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_POSITIVE_INTEGER
                ),
            ];
        }
        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): int {
        return (int)$validatedValue;
    }
}