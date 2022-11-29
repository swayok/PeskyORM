<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use Carbon\CarbonImmutable;
use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\RecordValue;
use Swayok\Utils\NormalizeValue;
use Swayok\Utils\ValidateValue;

abstract class ColumnValueFormatters
{
    public const FORMAT_DATE = 'date';
    public const FORMAT_DATE_TIME = 'date_time';
    public const FORMAT_TIME = 'time';
    public const FORMAT_UNIX_TS = 'unix_ts';
    public const FORMAT_CARBON = 'carbon';
    public const FORMAT_ARRAY = 'array';
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
            static::FORMAT_ARRAY => static::getJsonToArrayFormatter(),
            static::FORMAT_OBJECT => static::getJsonToObjectFormatter(),
        ];
    }

    public static function getTimestampToDateFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_DATE,
                static function (RecordValue $valueContainer): string {
                    $value = static::getSimpleValueFormContainer($valueContainer);
                    if (ValidateValue::isDateTime($value, true)) {
                        // $value converted to unix timestamp in ValidateValue::isDateTime()
                        return date(NormalizeValue::DATE_FORMAT, $value);
                    }
                    static::throwInvalidValueException($valueContainer, 'date-time', $value);
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
                static function (RecordValue $valueContainer): string {
                    $value = static::getSimpleValueFormContainer($valueContainer);
                    if (ValidateValue::isDateTime($value, true)) {
                        // $value converted to unix timestamp in ValidateValue::isDateTime()
                        return date(NormalizeValue::TIME_FORMAT, $value);
                    }
                    static::throwInvalidValueException($valueContainer, 'date-time', $value);
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
                static function (RecordValue $valueContainer): int {
                    $value = static::getSimpleValueFormContainer($valueContainer);
                    if (ValidateValue::isDateTime($value, true)) {
                        // $value converted to unix timestamp in ValidateValue::isDateTime()
                        return $value;
                    }
                    static::throwInvalidValueException($valueContainer, 'date-time', $value);
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
                static function (RecordValue $valueContainer): string {
                    $value = static::getSimpleValueFormContainer($valueContainer);
                    if (is_numeric($value) && $value > 0) {
                        return date('Y-m-d H:i:s', $value);
                    }
                    static::throwInvalidValueException($valueContainer, 'unix timestamp', $value);
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
                static function (RecordValue $valueContainer): CarbonImmutable {
                    $value = static::getSimpleValueFormContainer($valueContainer);
                    if (is_numeric($value)) {
                        $carbon = CarbonImmutable::createFromTimestampUTC($value);
                    } else {
                        $carbon = CarbonImmutable::parse($value);
                    }
                    if ($carbon->isValid()) {
                        return $carbon;
                    }
                    static::throwInvalidValueException($valueContainer, 'date-time', $value);
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
                static function (RecordValue $valueContainer): CarbonImmutable {
                    $value = static::getSimpleValueFormContainer($valueContainer);
                    $carbon = CarbonImmutable::parse($value)
                        ->startOfDay();
                    if ($carbon->isValid()) {
                        return $carbon;
                    }
                    static::throwInvalidValueException($valueContainer, 'date', $value);
                }
            );
        }
        return $formatter;
    }

    public static function getJsonToArrayFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(
                static::FORMAT_ARRAY,
                static function (RecordValue $valueContainer): array|string|bool|null|int|float {
                    $value = static::getSimpleValueFormContainer($valueContainer);
                    if (ValidateValue::isJson($value, true)) {
                        return $value;
                    }
                    // value conditionally decoded in ValidateValue::isJson()
                    static::throwInvalidValueException($valueContainer, 'json', $value);
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
                static function (RecordValue $valueContainer) {
                    $value = static::getSimpleValueFormContainer($valueContainer);
                    if (ValidateValue::isJson($value, true)) {
                        $targetClassName = $valueContainer->getColumn()
                            ->getObjectClassNameForValueToObjectFormatter();
                        // value conditionally decoded in ValidateValue::isJson()
                        if (is_array($value)) {
                            // value is array and can be converted to $targetClassName object or \stdClass object
                            return $targetClassName
                                ? $targetClassName::createObjectFromArray($value)
                                : json_decode(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), false, 512, JSON_THROW_ON_ERROR);
                            // encode-decode needed to be sure all nested arrays will also be encoded as \stdClass - not effective
                        }

                        if ($targetClassName) {
                            throw new \UnexpectedValueException('Record value must be a json string, array, or object');
                        }

                        return $value;
                    }
                    static::throwInvalidValueException($valueContainer, 'json', $value);
                }
            );
        }
        return $formatter;
    }

    /**
     * @throws \UnexpectedValueException
     */
    public static function getSimpleValueFormContainer(RecordValue $valueContainer): mixed
    {
        $value = $valueContainer->getValueOrDefault();
        if ($value instanceof DbExpr) {
            throw new \UnexpectedValueException('It is impossible to convert ' . DbExpr::class . ' object to anoter format');
        }
        return $value;
    }

    public static function wrapGetterIntoFormatter(string $format, \Closure $getter): \Closure
    {
        return static function (RecordValue $valueContainer) use ($getter, $format) {
            return $valueContainer->rememberPayload('format:' . $format, $getter);
        };
    }

    private static function throwInvalidValueException(
        RecordValue $valueContainer,
        string $expectedValueType,
        $value
    ): void {
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
        throw new \UnexpectedValueException($message, 0, $prevException);
    }
}