<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription;

// todo: convert to enum in 8.1
abstract class ColumnDescriptionDataType
{
    public const INT = 'integer';
    public const FLOAT = 'float';
    public const BOOL = 'boolean';
    public const STRING = 'string';
    public const TEXT = 'text';
    public const JSON = 'json';
    public const TIMESTAMP = 'timestamp';
    public const TIMESTAMP_WITH_TZ = 'timestamp_tz';
    public const DATE = 'date';
    public const TIME = 'time';
    public const TIME_WITH_TZ = 'time_tz';
    public const BLOB = 'blob';
}