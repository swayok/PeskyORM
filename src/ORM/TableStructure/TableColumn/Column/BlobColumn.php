<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;

class BlobColumn extends RealTableColumnAbstract
{
    use CanBeNullable;

    public function getDataType(): string
    {
        return TableColumnDataType::BLOB;
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
        if (!is_string($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_STRING
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
        return $validatedValue;
    }
}