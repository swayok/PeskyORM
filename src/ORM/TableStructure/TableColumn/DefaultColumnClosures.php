<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\RecordsCollection\RecordsSet;
use PeskyORM\Select\SelectQueryBuilderInterface;

/**
 * @deprecated
 */
class DefaultColumnClosures implements ColumnClosuresInterface
{

    /**
     * @throws \BadMethodCallException
     */
    public static function valueSetter(
        mixed $newValue,
        bool $isFromDb,
        RecordValueContainerInterface $valueContainer,
        bool $trustDataReceivedFromDb
    ): RecordValueContainerInterface {
        $column = $valueContainer->getColumn();
        if (!$isFromDb && $column->isReadonly()) {
            throw new \BadMethodCallException(
                "Column '{$column->getName()}' is read only."
            );
        }
        if ($isFromDb && $trustDataReceivedFromDb) {
            $normalziedValue = ColumnValueProcessingHelpers::normalizeValueReceivedFromDb($newValue, $column->getDataType());
            $valueContainer->setValue($newValue, $normalziedValue, true);
        } else {
            $preprocessedValue = call_user_func($column->getValuePreprocessor(), $newValue, $isFromDb, false, $column);
            if ($preprocessedValue === null && $column->hasDefaultValue()) {
                $preprocessedValue = $column->getValidDefaultValue();
            }
            if (
                $valueContainer->hasValue()
                && (
                    $valueContainer->getRawValue() === $preprocessedValue
                    || $valueContainer->getValue() === $preprocessedValue
                )
            ) {
                // received same value as current one (raw or normalized)
                if ($isFromDb && !$valueContainer->isItFromDb()) {
                    // value has changed its satatus to 'received from db'
                    $valueContainer->setIsFromDb(true);
                }
            } else {
                if ($valueContainer->hasValue()) {
                    // create new container
                    $valueContainer = $column->getNewRecordValueContainer($valueContainer->getRecord());
                }
                $errors = $column->validateValue($preprocessedValue, $isFromDb, false);
                if (count($errors) > 0) {
                    throw new InvalidDataException(
                        [$column->getName() => $errors],
                        $valueContainer->getRecord(),
                        $column,
                        $newValue
                    );
                }

                $normalziedValue = call_user_func(
                    $column->getValueNormalizer(),
                    $preprocessedValue,
                    $isFromDb,
                    $column
                );
                $valueContainer->setValue($newValue, $normalziedValue, $isFromDb);
            }
        }
        return $valueContainer;
    }

    public static function valuePreprocessor(
        mixed $value,
        bool $isFromDb,
        bool $isForValidation,
        TableColumn $column
    ): mixed {
        if ($isFromDb && !$isForValidation) {
            return $value;
        }
        if (is_string($value)) {
            if (!$isFromDb && $column->shouldTrimValues()) {
                $value = trim($value);
            }
            if ($value === '' && $column->shouldConvertEmptyStringToNull()) {
                return null;
            }
            if (!$isFromDb && $column->shouldLowercaseValues()) {
                $value = mb_strtolower($value);
            }
        } elseif ($value instanceof RecordsSet) {
            $value = $value->getOrmSelect();
        }
        return $value;
    }

    public static function valueGetter(RecordValueContainerInterface $valueContainer, ?string $format = null): mixed
    {
        $column = $valueContainer->getColumn();
        if ($format !== null) {
            return call_user_func(
                $column->getValueFormatter(),
                $valueContainer,
                $format
            );
        }

        if ($valueContainer->hasValue()) {
            return $valueContainer->getValue();
        }

        if (
            !$column->isPrimaryKey()
            && $column->hasDefaultValue()
            && !$valueContainer->getRecord()->existsInDb()
        ) {
            return $column->getValidDefaultValue();
        }

        $columnInfo = static::getRecordAndColumnInfoForException($valueContainer);
        if (!$column->hasDefaultValue()) {
            $defaultValueRestriction = 'is not provided.';
        } else {
            $defaultValueRestriction = 'cannot be used.';
        }
        throw new \BadMethodCallException(
            "Value for {$columnInfo} is not set and default value "
            . $defaultValueRestriction
        );
    }

    protected static function getRecordAndColumnInfoForException(
        RecordValueContainerInterface $valueContainer
    ): string {
        $record =  $valueContainer->getRecord();
        $pk = 'undefined';
        if (!$valueContainer->getColumn()->isPrimaryKey()) {
            try {
                $pk = $record->existsInDb() ? $record->getPrimaryKeyValue() : 'null';
            } catch (\Throwable) {
            }
        }
        return get_class($record) . '(#' . $pk . ')->' . $valueContainer->getColumn()->getName();
    }

    public static function valueExistenceChecker(RecordValueContainerInterface $valueContainer, bool $checkDefaultValue = false): bool
    {
        if ($valueContainer->hasValue()) {
            return true;
        }
        if (
            !$checkDefaultValue
            || $valueContainer->getColumn()->isPrimaryKey()
            || $valueContainer->getRecord()->existsInDb()
        ) {
            return false;
        }
        return $valueContainer->getColumn()->hasDefaultValue();
    }

    /**
     * @throws \UnexpectedValueException
     */
    public static function valueValidator(
        mixed $value,
        bool $isFromDb,
        bool $isForCondition,
        TableColumnInterface $column
    ): array {
        $errors = ColumnValueProcessingHelpers::isValidDbColumnValue(
            $column,
            $value,
            $isFromDb,
            $isForCondition,
            $column->getValidationErrorsMessages()
        );
        if (count($errors) > 0) {
            return $errors;
        }
        if ($value instanceof DbExpr || $value instanceof SelectQueryBuilderInterface) {
            // can't be validated in any other way
            return [];
        }

        $errors = call_user_func($column->getValueValidatorExtender(), $value, $isFromDb, $isForCondition, $column);
        if (!is_array($errors)) {
            throw new \UnexpectedValueException('Value validator extender closure must return an array');
        }

        return $errors;
    }

    public static function valueNormalizer(mixed $value, bool $isFromDb, TableColumnInterface $column): mixed
    {
        return $isFromDb
            ? ColumnValueProcessingHelpers::normalizeValueReceivedFromDb($value, $column->getDataType())
            : ColumnValueProcessingHelpers::normalizeValue($value, $column->getDataType());
    }

    public static function valueSavingExtender(RecordValueContainerInterface $valueContainer, bool $isUpdate): void
    {
    }

    public static function valueDeleteExtender(RecordValueContainerInterface $valueContainer, bool $deleteFiles): void
    {
    }

    public static function valueValidatorExtender(mixed $value, bool $isFromDb, TableColumnInterface $column): array
    {
        return [];
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function valueFormatter(RecordValueContainerInterface $valueContainer, string $format): mixed
    {
        $column = $valueContainer->getColumn();
        $customFormatters = $column->getCustomValueFormatters();
        if (isset($customFormatters[$format])) {
            return $customFormatters[$format]($valueContainer);
        }
        $typeFormatters = $column->getValueFormattersForColumnType();
        if (isset($typeFormatters[$format])) {
            return $typeFormatters[$format]($valueContainer);
        }
        $formats = $column->getValueFormattersNames();
        throw new \InvalidArgumentException(
            "Value format '{$format}' is not supported for column '{$column->getName()}'."
            . ' Supported formats: ' . (count($formats) ? implode(', ', $formats) : 'none')
        );
    }
}
