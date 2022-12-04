<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use Carbon\CarbonImmutable;
use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\Column\DateColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimeColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;
use PeskyORM\Select\SelectQueryBuilderInterface;
use PeskyORM\Utils\ValueTypeValidators;

abstract class ColumnValueFormatters
{
    public const FORMAT_DATE = 'date';
    public const FORMAT_DATE_TIME = 'date_time';
    public const FORMAT_TIME = 'time';
    public const FORMAT_UNIX_TS = 'unix_ts';
    public const FORMAT_CARBON = 'carbon';
    public const FORMAT_ARRAY = 'array';
    public const FORMAT_DECODED = 'decoded';
    public const FORMAT_OBJECT = 'object';

    public static function getFormattersForColumnType(string $columnType): array
    {
        static $map = null;
        if ($map === null) {
            $map = [
                TableColumn::TYPE_TIMESTAMP => static::getTimestampFormatters(),
                TableColumn::TYPE_DATE => static::getDateFormatters(),
                TableColumn::TYPE_TIME => static::getTimeFormatters(),
                TableColumn::TYPE_UNIX_TIMESTAMP => static::getUnixTimestampFormatters(),
                TableColumn::TYPE_JSON => static::getJsonFormatters(),
            ];
            $map[TableColumn::TYPE_TIMESTAMP_WITH_TZ] = $map[TableColumn::TYPE_TIMESTAMP];
            $map[TableColumn::TYPE_JSONB] = $map[TableColumn::TYPE_JSON];
        }
        return $map[$columnType] ?? [];
    }

    public static function getTimestampFormatters(): array
    {
        return [
            static::FORMAT_DATE => static::getTimestampToDateFormatter(),
            static::FORMAT_TIME => static::getTimestampToTimeFormatter(),
            static::FORMAT_UNIX_TS => static::getDateTimeToUnixTsFormatter(),
            static::FORMAT_CARBON => static::getTimestampToCarbonFormatter(),
        ];
    }

    public static function getUnixTimestampFormatters(): array
    {
        return [
            static::FORMAT_DATE_TIME => static::getUnixTimestampToDateTimeFormatter(),
            static::FORMAT_DATE => static::getTimestampToDateFormatter(),
            static::FORMAT_TIME => static::getTimestampToTimeFormatter(),
            static::FORMAT_CARBON => static::getTimestampToCarbonFormatter(),
        ];
    }

    public static function getDateFormatters(): array
    {
        return [
            static::FORMAT_UNIX_TS => static::getDateTimeToUnixTsFormatter(),
            static::FORMAT_CARBON => static::getDateToCarbonFormatter(),
        ];
    }

    public static function getTimeFormatters(): array
    {
        return [
            static::FORMAT_UNIX_TS => static::getDateTimeToUnixTsFormatter(),
        ];
    }

    public static function getJsonFormatters(): array
    {
        return [
            static::FORMAT_ARRAY => static::getJsonToDecodedValueFormatter(),
            static::FORMAT_DECODED => static::getJsonToDecodedValueFormatter(),
            static::FORMAT_OBJECT => static::getJsonToObjectFormatter(),
        ];
    }

    public static function getTimestampToDateFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_DATE,
                static function (RecordValueContainerInterface $valueContainer): string {
                    $value = static::getSimpleValueFromContainer($valueContainer);
                    if (!ValueTypeValidators::isTimestamp($value, true)) {
                        throw static::getInvalidValueException(
                            $valueContainer,
                            'date-time',
                            $value
                        );
                    }
                    if (!is_numeric($value)) {
                        $value = strtotime($value);
                    }
                    return date(DateColumn::FORMAT, $value);
                }
            );
        }
        return $formatter;
    }

    public static function getTimestampToTimeFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_TIME,
                static function (RecordValueContainerInterface $valueContainer): string {
                    $value = static::getSimpleValueFromContainer($valueContainer);
                    if (!ValueTypeValidators::isTimestamp($value, true)) {
                        throw static::getInvalidValueException(
                            $valueContainer,
                            'date-time',
                            $value
                        );
                    }
                    if (!is_numeric($value)) {
                        $value = strtotime($value);
                    }
                    return date(TimeColumn::FORMAT, $value);
                }
            );
        }
        return $formatter;
    }

    public static function getDateTimeToUnixTsFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_UNIX_TS,
                static function (RecordValueContainerInterface $valueContainer): int {
                    $value = static::getSimpleValueFromContainer($valueContainer);
                    if (!ValueTypeValidators::isTimestamp($value)) {
                        throw static::getInvalidValueException(
                            $valueContainer,
                            'date-time',
                            $value
                        );
                    }
                    return is_numeric($value) ? $value : strtotime($value);
                }
            );
        }
        return $formatter;
    }

    public static function getUnixTimestampToDateTimeFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_DATE_TIME,
                static function (RecordValueContainerInterface $valueContainer): string {
                    $value = static::getSimpleValueFromContainer($valueContainer);
                    if (!is_numeric($value) || $value <= 0) {
                        throw static::getInvalidValueException(
                            $valueContainer,
                            'unix timestamp',
                            $value
                        );
                    }
                    return date(TimestampColumn::FORMAT, $value);
                }
            );
        }
        return $formatter;
    }

    public static function getTimestampToCarbonFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_CARBON,
                static function (RecordValueContainerInterface $valueContainer): CarbonImmutable {
                    $value = static::getSimpleValueFromContainer($valueContainer);
                    if (is_numeric($value)) {
                        $carbon = CarbonImmutable::createFromTimestampUTC($value);
                    } else {
                        $carbon = CarbonImmutable::parse($value);
                    }
                    if (!$carbon->isValid()) {
                        throw static::getInvalidValueException(
                            $valueContainer,
                            'date-time',
                            $value
                        );
                    }
                    return $carbon;
                }
            );
        }
        return $formatter;
    }

    public static function getDateToCarbonFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_CARBON,
                static function (RecordValueContainerInterface $valueContainer): CarbonImmutable {
                    $value = static::getSimpleValueFromContainer($valueContainer);
                    $carbon = CarbonImmutable::parse($value)->startOfDay();
                    if (!$carbon->isValid()) {
                        throw static::getInvalidValueException(
                            $valueContainer,
                            'date',
                            $value
                        );
                    }
                    return $carbon;
                }
            );
        }
        return $formatter;
    }

    public static function getJsonToDecodedValueFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_DECODED,
                static function (RecordValueContainerInterface $valueContainer): array|string|bool|null|int|float {
                    $value = static::getSimpleValueFromContainer($valueContainer);
                    if (!ValueTypeValidators::isJson($value)) {
                        throw static::getInvalidValueException(
                            $valueContainer,
                            'json',
                            $value
                        );
                    }
                    return is_array($value)
                        ? $value
                        : json_decode((string)$value, true, JSON_THROW_ON_ERROR);
                }
            );
        }
        return $formatter;
    }

    public static function getJsonToObjectFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_OBJECT,
                static function (RecordValueContainerInterface $valueContainer) {
                    $value = static::getSimpleValueFromContainer($valueContainer);
                    if (!ValueTypeValidators::isJson($value)) {
                        throw static::getInvalidValueException(
                            $valueContainer,
                            'json',
                            $value
                        );
                    }
                    $targetClassName = null;
                    $column = $valueContainer->getColumn();
                    if ($column instanceof ConvertsValueToClassInstanceInterface) {
                        $targetClassName = $column->getClassNameForValueToClassInstanceConverter();
                    }
                    if (!is_array($value)) {
                        $value = json_decode((string)$value, true, JSON_THROW_ON_ERROR);
                    }
                    if (is_array($value)) {
                        if ($targetClassName) {
                            // Convert to $targetClassName object
                            return $targetClassName::createObjectFromArray($value);
                        }
                        // Convert to \stdClass object.
                        // Encode needed to be sure all nested arrays
                        // will also be encoded as \stdClass. Not effective.
                        $value = json_encode(
                            $value,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                        );
                        return json_decode(
                            $value,
                            false,
                            512,
                            JSON_THROW_ON_ERROR
                        );
                    }

                    if ($targetClassName) {
                        throw new \UnexpectedValueException(
                            'Record value must be a json string, array, or object'
                        );
                    }

                    return $value;
                }
            );
        }
        return $formatter;
    }

    /**
     * @throws \UnexpectedValueException
     */
    public static function getSimpleValueFromContainer(
        RecordValueContainerInterface $valueContainer
    ): mixed {
        $value = $valueContainer->getValue();
        if ($value instanceof DbExpr || $value instanceof SelectQueryBuilderInterface) {
            throw new \UnexpectedValueException(
                'It is impossible to convert ' . get_class($value) . ' instance to anoter format.'
            );
        }
        return $value;
    }

    public static function wrapGetterIntoFormatter(string $format, \Closure $getter): \Closure
    {
        return static function (RecordValueContainerInterface $valueContainer) use ($getter, $format) {
            return $valueContainer->rememberPayload('format:' . $format, $getter);
        };
    }

    private static function getInvalidValueException(
        RecordValueContainerInterface $valueContainer,
        string $expectedValueType,
        $value
    ): \UnexpectedValueException {
        if (!is_scalar($value)) {
            $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        $record = $valueContainer->getRecord();
        $prevException = null;
        $recordId = '(no PK value)';
        try {
            if ($record->existsInDb()) {
                $recordId = '(#' . $record->getPrimaryKeyValue() . ')';
            }
        } catch (\Throwable $prevException) {
        }
        $message = get_class($record) . $recordId . '->' . $valueContainer->getColumn()->getName()
            . ' contains invalid ' . $expectedValueType . ' value: [' . $value . ']';
        return new \UnexpectedValueException($message, 0, $prevException);
    }
}