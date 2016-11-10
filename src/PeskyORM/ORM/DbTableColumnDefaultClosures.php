<?php

namespace PeskyORM\ORM;

class DbTableColumnDefaultClosures {

    /**
     * @param mixed $newValue
     * @param boolean $isFromDb
     * @param DbRecordValue $valueContainer
     * @return DbRecordValue
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function valueSetter($newValue, $isFromDb, DbRecordValue $valueContainer) {
        $column = $valueContainer->getColumn();
        if (!$isFromDb && !$column->isValueCanBeSetOrChanged()) {
            throw new \BadMethodCallException(
                "Column '{$column->getName()}' restricts value modification"
            );
        }
        $preprocessedValue = call_user_func($column->getValuePreprocessor(), $newValue, $isFromDb, $column);
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
            $valueContainer->setRawValue($newValue, $preprocessedValue, $isFromDb);
            $errors = $column->validateValue($valueContainer, $isFromDb);
            if (count($errors) > 0) {
                $valueContainer->setValidationErrors($errors);
            } else {
                $normalziedValue = call_user_func(
                    $column->getValueNormalizer(),
                    $preprocessedValue,
                    $isFromDb,
                    $column
                );
                $valueContainer->setValidValue($normalziedValue, $newValue);
            }
        }
        return $valueContainer;
    }

    /**
     * Uses $column->convertsEmptyValueToNull(), $column->mustTrimValue() and $column->mustLowercaseValue()
     * @param DbTableColumn $column
     * @param mixed $value
     * @param bool $isFromDb
     * @return mixed
     */
    static public function valuePreprocessor($value, $isFromDb, DbTableColumn $column) {
        if (is_string($value)) {
            if ($column->isValueTrimmingRequired()) {
                $value = trim($value);
            }
            if ($value === '' && $column->isEmptyStringMustBeConvertedToNull()) {
                return null;
            }
            if ($column->isValueLowercasingRequired()) {
                $value = mb_strtolower($value);
            }
        }
        return $value;
    }

    /**
     * @param DbRecordValue $value
     * @param null|string $format
     * @return mixed
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function valueGetter(DbRecordValue $value, $format = null) {
        if ($format !== null) {
            if (!is_string($format) && !is_numeric($format)) {
                throw new \InvalidArgumentException(
                    "\$format argument for column '{$value->getColumn()->getName()}' must be a string or a number."
                );
            }
            $column = $value->getColumn();
            $formats = $column->getValueFormats();
            if (!$column->hasValueFormatter()) {
                throw new \InvalidArgumentException(
                    "\$format argument is not supported for column '{$value->getColumn()->getName()}'."
                        . ' You need to provide a value formatter in DbTableColumn.'
                );
            } else if (empty($formats) || in_array($format, $formats, true)) {
                return call_user_func($column->getValueFormatter(), $value, $format);
            } else {
                throw new \InvalidArgumentException(
                    "Value format named '{$format}' is not supported for column '{$value->getColumn()->getName()}'."
                        . ' Supported formats: ' . implode(', ', $formats)
                );
            }
        } else {
            return $value->getValueOrDefault();
        }
    }

    /**
     * @param DbRecordValue $value
     * @param bool $checkDefaultValue
     * @return bool
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    static public function valueExistenceChecker(DbRecordValue $value, $checkDefaultValue = false) {
        return $checkDefaultValue ? $value->hasValueOrDefault() : $value->hasValue();
    }

    /**
     * @param DbRecordValue|mixed $value
     * @param bool $isFromDb
     * @param DbTableColumn $column
     * @return array
     * @throws \PDOException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     */
    static public function valueValidator($value, $isFromDb, DbTableColumn $column) {
        if ($value instanceof DbRecordValue) {
            $value = $value->getValue();
        }
        $errors = DbRecordValueHelpers::isValidDbColumnValue(
            $column,
            $value,
            $column::getValidationErrorsLocalization()
        );
        if (count($errors) > 0) {
            return $errors;
        }
        $errors = call_user_func($column->getValueIsAllowedValidator(), $value, $isFromDb, $column);
        if (!is_array($errors)) {
            throw new \UnexpectedValueException('Allowed value validator closure must return an array');
        } else if (count($errors) > 0) {
            return $errors;
        }
        $errors = call_user_func($column->getValueValidatorExtender(), $value, $isFromDb, $column);
        if (!is_array($errors)) {
            throw new \UnexpectedValueException('Value validator extender closure must return an array');
        }
        return $errors;
    }

    /**
     * @param DbRecordValue|mixed $value
     * @param bool $isFromDb
     * @param DbTableColumn $column
     * @return array
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    static public function valueIsAllowedValidator($value, $isFromDb, DbTableColumn $column) {
        return DbRecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn(
            $column,
            $value,
            $column::getValidationErrorsLocalization()
        );
    }

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param DbTableColumn $column
     * @return mixed
     */
    static public function valueNormalizer($value, $isFromDb, DbTableColumn $column) {
        return DbRecordValueHelpers::normalizeValue($value, $column->getType());
    }

}