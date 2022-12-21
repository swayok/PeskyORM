<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\PasswordColumnInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CannotHaveDefaultValue;

class PasswordColumn extends RealTableColumnAbstract implements PasswordColumnInterface
{
    use CanBeNullable;
    use CannotHaveDefaultValue;

    protected \Closure $passwordHasher;
    protected \Closure $passwordChecker;

    public function __construct(string $name = 'password')
    {
        parent::__construct($name);
        $this->setPasswordHasher(
            function (string $value): string {
                return password_hash($value, PASSWORD_BCRYPT, [
                    'cost' => 10,
                ]);
            },
            function (string $plainValue, string $hashedValue): bool {
                return password_verify($plainValue, $hashedValue);
            }
        );
    }

    public function getDataType(): string
    {
        return TableColumnDataType::STRING;
    }

    public function isPrivateValues(): bool
    {
        return true;
    }

    final public function isAutoUpdatingValues(): bool
    {
        return false;
    }

    public function hashPassword(string $value): string
    {
        return call_user_func($this->passwordHasher, $value);
    }

    public function verifyPassword(string $plainValue, string $hashedValue): bool
    {
        return call_user_func($this->passwordChecker, $plainValue, $hashedValue);
    }

    /**
     * Set custom password hashing algorythm.
     * Closure signature:
     * function (string $value): string;
     */
    public function setPasswordHasher(\Closure $hasher, ?\Closure $verifier = null): static
    {
        $this->passwordHasher = $hasher;
        $this->passwordChecker = $verifier;
        return $this;
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_string($value)) {
            return $this->normalizePasswordString($value, $isFromDb);
        }
        return $value;
    }

    public function normalizePasswordString(string $value, bool $isFromDb): ?string
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
        $hashInfo = password_get_info($normalizedValue);
        if ($isFromDb) {
            if (!$hashInfo['algo']) {
                return [
                    $this->getValueValidationMessage(
                        ColumnValueValidationMessagesInterface::VALUE_MUST_BE_PASSWORD_HASH
                    ),
                ];
            }
        } elseif ($hashInfo['algo']) {
            // hashed password received as not db value
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_PLAIN_PASSWORD
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