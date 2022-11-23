<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

interface ColumnClosuresInterface
{
    
    /**
     * Set value. Should also normalize and validate value.
     * $value may be an instance of AbstractSelect, UploadedFile or DbExpr classes but
     * not instance of RecordValue class.
     * $trustDataReceivedFromDb = true tells setter that $newValue is trusted when $isFromDb === true.
     * This usually means that normalization and validation of $newValue is not needed.
     */
    public static function valueSetter(mixed $newValue, bool $isFromDb, RecordValue $valueContainer, bool $trustDataReceivedFromDb): RecordValue;
    
    /**
     * Slightly modify value before validation and value setter.
     * $value may be an instance of AbstractSelect, UploadedFile or DbExpr classes but
     * not instance of RecordValue class.
     * Uses $column->isEmptyStringMustBeConvertedToNull(), $column->isValueLowercasingRequired()
     * and $column->isValueTrimmingRequired().
     */
    public static function valuePreprocessor(mixed $value, bool $isFromDb, bool $isForValidation, Column $column): mixed;
    
    /**
     * Get value.
     * Uses valueFormatter if $format is not null.
     */
    public static function valueGetter(RecordValue $valueContainer, ?string $format = null): mixed;
    
    /**
     * Tests if value is set.
     */
    public static function valueExistenceChecker(RecordValue $valueContainer, bool $checkDefaultValue = false): bool;
    
    /**
     * Validates value. Uses valueValidatorExtender and valueExistenceChecker.
     * $value may be instance of AbstractSelect, UploadedFile, DbExpr or RecordValue classes.
     */
    public static function valueValidator(mixed $value, bool $isFromDb, bool $isForCondition, Column $column): array;
    
    /**
     * Extends value validation in addition to valueValidator.
     * Returns array of error messages or empty array if no errors.
     * $value may be an instance of AbstractSelect, UploadedFile or DbExpr classes but
     * not instance of RecordValue class.
     */
    public static function valueValidatorExtender(mixed $value, bool $isFromDb, Column $column): array;
    
    /**
     * Validates if value is allowed.
     * $value may be an instance of AbstractSelect, UploadedFile, DbExpr or RecordValue classes.
     */
    public static function valueIsAllowedValidator(mixed $value, bool $isFromDb, Column $column): array;
    
    /**
     * Normalize value to fit column's data type.
     * $value may be an instance of AbstractSelect, UploadedFile or DbExpr classes but
     * not instance of RecordValue class.
     */
    public static function valueNormalizer(mixed $value, bool $isFromDb, Column $column): mixed;
    
    /**
     * Additional actions after value saving to DB (or instead of saving if column does not exist in DB).
     */
    public static function valueSavingExtender(RecordValue $valueContainer, bool $isUpdate, array $savedData): void;
    
    /**
     * Additional actions after record deleted from DB.
     */
    public static function valueDeleteExtender(RecordValue $valueContainer, bool $deleteFiles): void;
    
    /**
     * Formats value according to required $format.
     */
    public static function valueFormatter(RecordValue $valueContainer, string $format): mixed;
    
}
