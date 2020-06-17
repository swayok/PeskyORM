<?php

namespace PeskyORM\ORM;

interface ColumnClosuresInterface {

    /**
     * Set value. Should also normalize and validate value
     * @param mixed $newValue
     * @param boolean $isFromDb
     * @param RecordValue $valueContainer
     * @param bool $trustDataReceivedFromDb - tells setter that $newValue is trusted when $isFromDb === true
     *      This usually means that normalization and validation not needed
     * @return RecordValue
     */
    static public function valueSetter($newValue, $isFromDb, RecordValue $valueContainer, $trustDataReceivedFromDb);

    /**
     * Slightly modify value before validation and value setter. Uses $column->isEmptyStringMustBeConvertedToNull(),
     * $column->isValueLowercasingRequired() and $column->isValueTrimmingRequired()
     * @param Column $column
     * @param mixed $value
     * @param bool $isFromDb
     * @param bool $isForValidation
     * @return mixed
     */
    static public function valuePreprocessor($value, bool $isFromDb, bool $isForValidation, Column $column);

    /**
     * Get value
     * @param RecordValue $value
     * @param null|string $format
     * @return mixed
     */
    static public function valueGetter(RecordValue $value, $format = null);

    /**
     * Tests if value is set
     * @param RecordValue $valueContainer
     * @param bool $checkDefaultValue
     * @return bool
     */
    static public function valueExistenceChecker(RecordValue $valueContainer, $checkDefaultValue = false);

    /**
     * Validates value. Uses valueValidatorExtender
     * @param RecordValue|mixed $value
     * @param bool $isFromDb
     * @param bool $isForCondition
     * @param Column $column
     * @return array
     */
    static public function valueValidator($value, $isFromDb, $isForCondition, Column $column);

    /**
     * Extends value validation in addition to valueValidator
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array - list of error messages (empty list = no errors)
     */
    static public function valueValidatorExtender($value, $isFromDb, Column $column);

    /**
     * Validates if value is allowed
     * @param RecordValue|mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array
     */
    static public function valueIsAllowedValidator($value, $isFromDb, Column $column);

    /**
     * Normalize value to fit column's data type
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return mixed
     */
    static public function valueNormalizer($value, $isFromDb, Column $column);

    /**
     * Additional actions after value saving to DB (or instead of saving if column does not exist in DB)
     * @param RecordValue $valueContainer
     * @param bool $isUpdate
     * @param array $savedData
     * @return void
     */
    static public function valueSavingExtender(RecordValue $valueContainer, $isUpdate, array $savedData);

    /**
     * Additional actions after record deleted from DB
     * @param RecordValue $valueContainer
     * @param bool $deleteFiles
     * @return void
     */
    static public function valueDeleteExtender(RecordValue $valueContainer, $deleteFiles);

    /**
     * Formats value according to required $format
     * @param RecordValue $valueContainer
     * @param string $format
     * @return mixed
     */
    static public function valueFormatter(RecordValue $valueContainer, $format);

    /**
     * List of available formatters for a column. Required for service purposes.
     * @param Column $column
     * @return array
     */
    static public function getValueFormats(Column $column);

}
