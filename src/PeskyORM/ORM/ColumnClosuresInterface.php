<?php

namespace PeskyORM\ORM;

interface ColumnClosuresInterface {

    /**
     * @param mixed $newValue
     * @param boolean $isFromDb
     * @param RecordValue $valueContainer
     * @return RecordValue
     */
    static public function valueSetter($newValue, $isFromDb, RecordValue $valueContainer);

    /**
     * @param Column $column
     * @param mixed $value
     * @param bool $isFromDb
     * @return mixed
     */
    static public function valuePreprocessor($value, $isFromDb, Column $column);

    /**
     * @param RecordValue $value
     * @param null|string $format
     * @return mixed
     */
    static public function valueGetter(RecordValue $value, $format = null);

    /**
     * @param RecordValue $value
     * @param bool $checkDefaultValue
     * @return bool
     */
    static public function valueExistenceChecker(RecordValue $value, $checkDefaultValue = false);

    /**
     * @param RecordValue|mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array
     */
    static public function valueValidator($value, $isFromDb, Column $column);

    /**
     * @param RecordValue|mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array
     */
    static public function valueIsAllowedValidator($value, $isFromDb, Column $column);

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return mixed
     */
    static public function valueNormalizer($value, $isFromDb, Column $column);

    /**
     * @param RecordValue $valueContainer
     * @param bool $isUpdate
     * @param array $savedData
     * @param Record $record
     * @return void
     */
    static public function valueSavingExtender(RecordValue $valueContainer, $isUpdate, array $savedData, Record $record);

    /**
     * @param RecordValue $valueContainer
     * @param Record $record
     * @param bool $deleteFiles
     * @return void
     */
    static public function valueDeleteExtender(RecordValue $valueContainer, Record $record, $deleteFiles);

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array - list of error messages (empty list = no errors)
     */
    static public function valueValidatorExtender($value, $isFromDb, Column $column);

}