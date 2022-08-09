<?php

namespace PeskyORM\ORM;

use Carbon\CarbonImmutable;
use PeskyORM\Core\DbExpr;
use Swayok\Utils\NormalizeValue;
use Swayok\Utils\ValidateValue;

abstract class RecordValueFormatters
{
    
    public const FORMAT_DATE = 'date';
    public const FORMAT_DATE_TIME = 'date_time';
    public const FORMAT_TIME = 'time';
    public const FORMAT_UNIX_TS = 'unix_ts';
    public const FORMAT_CARBON = 'carbon';
    public const FORMAT_ARRAY = 'array';
    public const FORMAT_OBJECT = 'object';
    
    static public function getFormattersForColumnType(string $columnType): array
    {
        static $map = null;
        if ($map === null) {
            $map = [
                Column::TYPE_TIMESTAMP => static::getTimestampFormatters(),
                Column::TYPE_DATE => static::getDateFormatters(),
                Column::TYPE_TIME => static::getTimeFormatters(),
                Column::TYPE_UNIX_TIMESTAMP => static::getUnixTimestampFormatters(),
                Column::TYPE_JSON => static::getJsonFormatters(),
            ];
            $map[Column::TYPE_TIMESTAMP_WITH_TZ] = $map[Column::TYPE_TIMESTAMP];
            $map[Column::TYPE_JSONB] = $map[Column::TYPE_JSON];
        }
        return $map[$columnType] ?? [];
    }
    
    static public function getTimestampFormatters(): array
    {
        return [
            static::FORMAT_DATE => static::getTimestampToDateFormatter(),
            static::FORMAT_TIME => static::getTimestampToTimeFormatter(),
            static::FORMAT_UNIX_TS => static::getDateTimeToUnixTsFormatter(),
            static::FORMAT_CARBON => static::getTimestampToCarbonFormatter(),
        ];
    }
    
    static public function getUnixTimestampFormatters(): array
    {
        return [
            static::FORMAT_DATE_TIME => static::getUnixTimestampToDateTimeFormatter(),
            static::FORMAT_DATE => static::getTimestampToDateFormatter(),
            static::FORMAT_TIME => static::getTimestampToTimeFormatter(),
            static::FORMAT_CARBON => static::getTimestampToCarbonFormatter(),
        ];
    }
    
    static public function getDateFormatters(): array
    {
        return [
            static::FORMAT_UNIX_TS => static::getDateTimeToUnixTsFormatter(),
            static::FORMAT_CARBON => static::getDateToCarbonFormatter(),
        ];
    }
    
    static public function getTimeFormatters(): array
    {
        return [
            static::FORMAT_UNIX_TS => static::getDateTimeToUnixTsFormatter(),
        ];
    }
    
    static public function getJsonFormatters(): array
    {
        return [
            static::FORMAT_ARRAY => static::getJsonToArrayFormatter(),
            static::FORMAT_OBJECT => static::getJsonToObjectFormatter(),
        ];
    }
    
    static public function getTimestampToDateFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(static::FORMAT_DATE, function (RecordValue $valueContainer): string {
                $value = static::getSimpleValueFormContainer($valueContainer);
                if (ValidateValue::isDateTime($value, true)) {
                    // $value converted to unix timestamp in ValidateValue::isDateTime()
                    return date(NormalizeValue::DATE_FORMAT, $value);
                }
                static::throwInvalidValueException($valueContainer, 'date-time', $value);
            });
        }
        return $formatter;
    }
    
    static public function getTimestampToTimeFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(static::FORMAT_TIME, function (RecordValue $valueContainer): string {
                $value = static::getSimpleValueFormContainer($valueContainer);
                if (ValidateValue::isDateTime($value, true)) {
                    // $value converted to unix timestamp in ValidateValue::isDateTime()
                    return date(NormalizeValue::TIME_FORMAT, $value);
                }
                static::throwInvalidValueException($valueContainer, 'date-time', $value);
            });
        }
        return $formatter;
    }
    
    static public function getDateTimeToUnixTsFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(static::FORMAT_UNIX_TS, function (RecordValue $valueContainer): int {
                $value = static::getSimpleValueFormContainer($valueContainer);
                if (ValidateValue::isDateTime($value, true)) {
                    // $value converted to unix timestamp in ValidateValue::isDateTime()
                    return $value;
                }
                static::throwInvalidValueException($valueContainer, 'date-time', $value);
            });
        }
        return $formatter;
    }
    
    static public function getUnixTimestampToDateTimeFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(static::FORMAT_DATE_TIME, function (RecordValue $valueContainer): string {
                $value = static::getSimpleValueFormContainer($valueContainer);
                if (is_numeric($value) && $value > 0) {
                    return date('Y-m-d H:i:s', $value);
                }
                static::throwInvalidValueException($valueContainer, 'unix timestamp', $value);
            });
        }
        return $formatter;
    }
    
    static public function getTimestampToCarbonFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(static::FORMAT_CARBON, function (RecordValue $valueContainer): CarbonImmutable {
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
            });
        }
        return $formatter;
    }
    
    static public function getDateToCarbonFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(static::FORMAT_CARBON, function (RecordValue $valueContainer): CarbonImmutable {
                $value = static::getSimpleValueFormContainer($valueContainer);
                $carbon = CarbonImmutable::parse($value)
                    ->startOfDay();
                if ($carbon->isValid()) {
                    return $carbon;
                }
                static::throwInvalidValueException($valueContainer, 'date', $value);
            });
        }
        return $formatter;
    }
    
    static public function getJsonToArrayFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            // todo: add return type in php8+: array|string|bool|null|int|float
            $formatter = static::wrapGetterIntoFormatter(static::FORMAT_ARRAY, function (RecordValue $valueContainer) {
                $value = static::getSimpleValueFormContainer($valueContainer);
                if (ValidateValue::isJson($value, true)) {
                    return $value;
                }
                // value conditionally decoded in ValidateValue::isJson()
                static::throwInvalidValueException($valueContainer, 'json', $value);
            });
        }
        return $formatter;
    }
    
    static public function getJsonToObjectFormatter(): \Closure
    {
        static $formatter = null;
        if (!$formatter) {
            $formatter = static::wrapGetterIntoFormatter(static::FORMAT_OBJECT, function (RecordValue $valueContainer) {
                $value = static::getSimpleValueFormContainer($valueContainer);
                if (ValidateValue::isJson($value, true)) {
                    $targetClassName = $valueContainer->getColumn()
                        ->getObjectClassNameForValueToObjectFormatter();
                    // value conditionally decoded in ValidateValue::isJson()
                    if (is_array($value)) {
                        // value is array and can be converted to $targetClassName object or \stdClass object
                        return $targetClassName
                            ? $targetClassName::createObjectFromArray($value)
                            : json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), false);
                        // encode-decode needed to be sure all nested arrays will also be encoded as \stdClass - not effective
                    } elseif ($targetClassName) {
                        throw new \UnexpectedValueException('Record value must be a json string, array, or object');
                    } else {
                        return $value;
                    }
                }
                static::throwInvalidValueException($valueContainer, 'json', $value);
            });
        }
        return $formatter;
    }
    
    /**
     * @return mixed
     * @throws \UnexpectedValueException
     */
    static public function getSimpleValueFormContainer(RecordValue $valueContainer)
    {
        $value = $valueContainer->getValueOrDefault();
        if ($value instanceof DbExpr) {
            throw new \UnexpectedValueException('It is impossible to convert ' . DbExpr::class . ' object to anoter format');
        }
        return $value;
    }
    
    static public function wrapGetterIntoFormatter(string $format, \Closure $getter): \Closure
    {
        return function (RecordValue $valueContainer) use ($getter, $format) {
            return $valueContainer->getCustomInfo('format:' . $format, $getter, true);
        };
    }
    
    static private function throwInvalidValueException(RecordValue $valueContainer, string $expectedValueType, $value)
    {
        if (!is_scalar($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
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
        throw new \UnexpectedValueException(
            get_class($record) . $recordId . '->' . $valueContainer->getColumn()
                ->getName()
            . ' contains invalid ' . $expectedValueType . ' value: [' . $value . ']',
            0,
            $prevException
        );
    }
}