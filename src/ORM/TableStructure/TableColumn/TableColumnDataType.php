<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

// todo: convert to enum in 8.1
abstract class TableColumnDataType
{
    public const INT = 'integer';
    public const FLOAT = 'float';
    public const BOOL = 'boolean';
    public const STRING = 'string';
    public const TEXT = 'text';
    public const JSON = 'json';
    public const ARRAY = 'array';
    public const TIMESTAMP = 'timestamp';
    public const UNIX_TIMESTAMP = 'unix_timestamp';
    public const DATE = 'date';
    public const TIME = 'time';
    public const TIMEZONE_OFFSET = 'timezone_offset';
    public const IPV4_ADDRESS = 'ip';
    public const FILE = 'file';
    public const IMAGE = 'image';
    public const BLOB = 'blob';
    public const VIRTUAL = 'virtual';
}