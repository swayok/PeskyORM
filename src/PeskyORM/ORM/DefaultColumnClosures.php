<?php

namespace PeskyORM\ORM;

class DefaultColumnClosures implements ColumnClosuresInterface {

    /**
     * @param mixed $newValue
     * @param boolean $isFromDb
     * @param RecordValue $valueContainer
     * @param bool $trustDataReceivedFromDb
     * @return RecordValue
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function valueSetter($newValue, $isFromDb, RecordValue $valueContainer, $trustDataReceivedFromDb) {
        $column = $valueContainer->getColumn();
        if (!$isFromDb && !$column->isValueCanBeSetOrChanged()) {
            throw new \BadMethodCallException(
                "Column '{$column->getName()}' restricts value modification"
            );
        }
        if ($isFromDb && $trustDataReceivedFromDb) {
            $valueContainer->setRawValue($newValue, $newValue, true);
            $valueContainer->setValidValue($newValue, $newValue);
        } else {
            $preprocessedValue = call_user_func($column->getValuePreprocessor(), $newValue, $isFromDb, $column);
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
                $valueContainer->setRawValue($newValue, $preprocessedValue, $isFromDb);
                $errors = $column->validateValue($valueContainer->getValue(), $isFromDb);
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
        }
        return $valueContainer;
    }

    /**
     * Uses $column->convertsEmptyValueToNull(), $column->mustTrimValue() and $column->mustLowercaseValue()
     * @param Column $column
     * @param mixed $value
     * @param bool $isFromDb
     * @return mixed
     */
    static public function valuePreprocessor($value, $isFromDb, Column $column) {
        if (is_string($value)) {
            if (!$isFromDb && $column->isValueTrimmingRequired()) {
                $value = trim($value);
            }
            if ($value === '' && $column->isEmptyStringMustBeConvertedToNull()) {
                return null;
            }
            if (!$isFromDb && $column->isValueLowercasingRequired()) {
                $value = mb_strtolower($value);
            }
        }
        return $value;
    }

    /**
     * @param RecordValue $value
     * @param null|string $format
     * @return mixed
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function valueGetter(RecordValue $value, $format = null) {
        if ($format !== null) {
            if (!is_string($format) && !is_numeric($format)) {
                throw new \InvalidArgumentException(
                    "\$format argument for column '{$value->getColumn()->getName()}' must be a string or a number."
                );
            }
            return call_user_func($value->getColumn()->getValueFormatter(), $value, $format);
        } else {
            return $value->getValueOrDefault();
        }
    }

    /**
     * @param RecordValue $valueContainer
     * @param bool $checkDefaultValue
     * @return bool
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    static public function valueExistenceChecker(RecordValue $valueContainer, $checkDefaultValue = false) {
        return $checkDefaultValue ? $valueContainer->hasValueOrDefault() : $valueContainer->hasValue();
    }

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     */
    static public function valueValidator($value, $isFromDb, Column $column) {
        $errors = RecordValueHelpers::isValidDbColumnValue(
            $column,
            $value,
            $isFromDb,
            $column::getValidationErrorsMessages()
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
     * @param RecordValue|mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    static public function valueIsAllowedValidator($value, $isFromDb, Column $column) {
        return RecordValueHelpers::isValueWithinTheAllowedValuesOfTheColumn(
            $column,
            $value,
            $isFromDb,
            $column::getValidationErrorsMessages()
        );
    }

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return mixed
     */
    static public function valueNormalizer($value, $isFromDb, Column $column) {
        return RecordValueHelpers::normalizeValue($value, $column->getType());
    }

    /**
     * @param RecordValue $valueContainer
     * @param bool $isUpdate
     * @param array $savedData
     * @return void
     */
    static public function valueSavingExtender(RecordValue $valueContainer, $isUpdate, array $savedData) {

    }

    /**
     * @param RecordValue $valueContainer
     * @param bool $deleteFiles
     * @return void
     */
    static public function valueDeleteExtender(RecordValue $valueContainer, $deleteFiles) {

    }

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column $column
     * @return array - list of error messages (empty list = no errors)
     */
    static public function valueValidatorExtender($value, $isFromDb, Column $column) {
        return [];
    }

    /**
     * Formats value according to required $format
     * @param RecordValue $valueContainer
     * @param string $format
     * @return mixed
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function valueFormatter(RecordValue $valueContainer, $format) {
        $column = $valueContainer->getColumn();
        list ($formatter, $formats) = RecordValueHelpers::getValueFormatterAndFormatsByType($column->getType());
        if (!in_array($format, $formats, true)) {
            throw new \InvalidArgumentException(
                "Value format '{$format}' is not supported for column '{$column->getName()}'."
                    . ' Supported formats: ' . (count($formats) ? implode(', ', $formats) : 'none')
            );
        }
        return call_user_func($formatter, $valueContainer, $format);
    }
}