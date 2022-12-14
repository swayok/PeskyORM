<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CannotHaveDefaultValue;

class BlobColumn extends RealTableColumnAbstract
{
    use CanBeNullable;
    use CannotHaveDefaultValue;

    public function getDataType(): string
    {
        return TableColumnDataType::BLOB;
    }

    final public function isAutoUpdatingValues(): bool
    {
        return false;
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if ($value === '') {
            $value = null;
        }
        return $value;
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if ($isFromDb) {
            // blobs received from DB are always of a resource type
            if (!is_resource($normalizedValue)) {
                return [
                    $this->getValueValidationMessage(
                        ColumnValueValidationMessagesInterface::VALUE_MUST_BE_RESOURCE
                    ),
                ];
            }
            return [];
        }
        if (!is_resource($normalizedValue) && !is_string($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_STRING_OR_RESOURCE
                ),
            ];
        }
        return [];
    }

    /**
     * @return string|resource
     */
    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): mixed {
        if ($isFromDb || !is_resource($validatedValue)) {
            return $validatedValue;
        }
        return stream_get_contents($validatedValue);
    }
}