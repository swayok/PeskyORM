<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\AbstractSelect;
use PeskyORM\Core\DbExpr;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ColumnClosuresInterface
{
    
    /**
     * Set value. Should also normalize and validate value
     * @param mixed $newValue
     * @param boolean $isFromDb
     * @param RecordValue $valueContainer
     * @param bool $trustDataReceivedFromDb - tells setter that $newValue is trusted when $isFromDb === true
     *      This usually means that normalization and validation not needed
     * @return RecordValue
     */
    public static function valueSetter($newValue, bool $isFromDb, RecordValue $valueContainer, bool $trustDataReceivedFromDb): RecordValue;
    
    /**
     * Slightly modify value before validation and value setter. Uses $column->isEmptyStringMustBeConvertedToNull(),
     * $column->isValueLowercasingRequired() and $column->isValueTrimmingRequired()
     * @param Column $column
     * @param mixed $value
     * @param bool $isFromDb
     * @param bool $isForValidation
     * @return mixed
     */
    public static function valuePreprocessor($value, bool $isFromDb, bool $isForValidation, Column $column);
    
    /**
     * Get value
     * @param RecordValue $valueContainer
     * @param string|null $format
     * @return mixed
     */
    public static function valueGetter(RecordValue $valueContainer, ?string $format = null);
    
    /**
     * Tests if value is set
     * @param RecordValue $valueContainer
     * @param bool $checkDefaultValue
     * @return bool
     */
    public static function valueExistenceChecker(RecordValue $valueContainer, bool $checkDefaultValue = false): bool;
    
    /**
     * Validates value. Uses valueValidatorExtender
     * @param RecordValue|mixed $value
     * @param bool $isFromDb
     * @param bool $isForCondition
     * @param Column $column
     * @return array
     */
    public static function valueValidator($value, bool $isFromDb, bool $isForCondition, Column $column): array;
    
    /**
     * Extends value validation in addition to valueValidator
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array - list of error messages (empty list = no errors)
     */
    public static function valueValidatorExtender($value, bool $isFromDb, Column $column): array;
    
    /**
     * Validates if value is allowed
     * @param RecordValue|mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array
     */
    public static function valueIsAllowedValidator($value, bool $isFromDb, Column $column): array;
    
    /**
     * Normalize value to fit column's data type
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return AbstractSelect|bool|DbExpr|float|int|string|UploadedFile|null
     */
    public static function valueNormalizer($value, bool $isFromDb, Column $column);
    
    /**
     * Additional actions after value saving to DB (or instead of saving if column does not exist in DB)
     */
    public static function valueSavingExtender(RecordValue $valueContainer, bool $isUpdate, array $savedData): void;
    
    /**
     * Additional actions after record deleted from DB
     */
    public static function valueDeleteExtender(RecordValue $valueContainer, bool $deleteFiles): void;
    
    /**
     * Formats value according to required $format
     * @param RecordValue $valueContainer
     * @param string $format
     * @return mixed
     */
    public static function valueFormatter(RecordValue $valueContainer, string $format);
    
}
