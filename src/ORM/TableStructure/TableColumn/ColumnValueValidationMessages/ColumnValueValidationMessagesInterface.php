<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages;

interface ColumnValueValidationMessagesInterface
{
    public const VALUE_CANNOT_BE_NULL = 'value_cannot_be_null';
    public const VALUE_MUST_BE_BOOLEAN = 'value_must_be_boolean';
    public const VALUE_MUST_BE_INTEGER = 'value_must_be_integer';
    public const VALUE_MUST_BE_POSITIVE_INTEGER = 'value_must_be_positive_integer';
    public const VALUE_MUST_BE_FLOAT = 'value_must_be_float';
    public const VALUE_MUST_BE_IMAGE = 'value_must_be_image';
    public const VALUE_MUST_BE_FILE = 'value_must_be_file';
    public const VALUE_MUST_BE_JSON_OR_JSONABLE = 'value_must_be_json_or_jsonable';
    public const VALUE_MUST_BE_JSON_ARRAY_OR_OBJECT = 'value_must_be_json_array_or_object';
    public const VALUE_MUST_BE_JSON_ARRAY = 'value_must_be_json_array';
    public const VALUE_MUST_BE_JSON_OBJECT = 'value_must_be_json_object';
    public const VALUE_MUST_BE_IPV4_ADDRESS = 'value_must_be_ipv4_address';
    public const VALUE_MUST_BE_EMAIL = 'value_must_be_email';
    public const VALUE_MUST_BE_TIMEZONE_OFFSET = 'value_must_be_timezone_offset';
    public const VALUE_MUST_BE_TIMESTAMP = 'value_must_be_timestamp';
    public const VALUE_MUST_BE_TIMESTAMP_OR_INTEGER = 'value_must_be_unix_timestamp_or_integer';
    public const VALUE_MUST_BE_TIME = 'value_must_be_time';
    public const VALUE_MUST_BE_DATE = 'value_must_be_date';
    public const VALUE_MUST_BE_STRING = 'value_must_be_string';
    public const VALUE_MUST_BE_ARRAY = 'value_must_be_array';
    public const VALUE_MUST_BE_RESOURCE = 'value_must_be_resource';
    public const VALUE_FROM_DB_CANNOT_BE_DB_EXPRESSION = 'value_from_db_cannot_be_db_expression';
    public const VALUE_FROM_DB_CANNOT_BE_QUERY_BUILDER = 'value_from_db_cannot_be_query_builder';

    public function getMessage(string $messageId): string;
}