<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;

class PasswordColumn extends RealTableColumnAbstract
{
    use CanBeNullable;

    protected ?\Closure $passwordHasher = null;

    public function isPrivateValues(): bool
    {
        return true;
    }

    public function getDataType(): string
    {
        return TableColumnDataType::STRING;
    }

    public function hashPassword(string $value): string
    {
        if ($this->passwordHasher) {
            return call_user_func($this->passwordHasher, $value);
        }
        return password_hash($value, PASSWORD_BCRYPT, [
            'cost' => 10,
        ]);
    }

    /**
     * Set custom password hashing algorythm.
     * Closure signature:
     * function (string $value): string;
     */
    public function setPasswordHasher(\Closure $hasher): static
    {
        $this->passwordHasher = $hasher;
        return $this;
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_string($value) || is_numeric($value)) {
            return $this->normalizeStringValue((string)$value, $isFromDb);
        }
        return $value;
    }

    protected function normalizeStringValue(string $value, bool $isFromDb): ?string
    {
        if ($isFromDb) {
            // do not modify DB value to avoid unintended changes
            return $value;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!is_string($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_STRING
                ),
            ];
        }
        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): string {
        if ($isFromDb) {
            return (string)$validatedValue;
        }
        return $this->hashPassword($validatedValue);
    }
}