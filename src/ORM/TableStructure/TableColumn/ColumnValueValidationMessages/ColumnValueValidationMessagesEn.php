<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages;

class ColumnValueValidationMessagesEn implements ColumnValueValidationMessagesInterface
{
    protected array $messages = [
        self::EXCEPTION_MESSAGE => 'Validation errors: %s.',
        self::VALUE_CANNOT_BE_NULL => 'Null value is not allowed.',
        self::VALUE_MUST_BE_BOOLEAN => 'Boolean value expected.',
        self::VALUE_MUST_BE_INTEGER => 'Integer value expected.',
        self::VALUE_MUST_BE_POSITIVE_INTEGER => 'Value must be a positive integer number.',
        self::VALUE_MUST_BE_FLOAT => 'Numeric value expected.',
        self::VALUE_MUST_BE_IMAGE => 'Uploaded image info expected.',
        self::VALUE_MUST_BE_FILE => 'Uploaded file info expected.',
        self::VALUE_MUST_BE_JSON_OR_JSONABLE => 'Value must be a json-encoded string or have jsonable type.',
        self::VALUE_MUST_BE_JSON_ARRAY_OR_OBJECT => 'Value must be a json-encoded array, json-encoded object or PHP array.',
        self::VALUE_MUST_BE_JSON_ARRAY => 'Value must be a json-encoded indexed array or indexed PHP array.',
        self::VALUE_MUST_BE_JSON_OBJECT => 'Value must be a json-encoded key-value object or associative PHP array.',
        self::VALUE_MUST_BE_IPV4_ADDRESS => 'Value must be an IPv4 address.',
        self::VALUE_MUST_BE_EMAIL => 'Value must be an email.',
        self::VALUE_MUST_BE_TIMEZONE => 'Value must be a valid timezone name or UTC timezone offset from -12:00 to +14:00.',
        self::VALUE_MUST_BE_TIMESTAMP => 'Value must be a valid timestamp.',
        self::VALUE_MUST_BE_TIMESTAMP_OR_INTEGER => 'Value must be a valid timestamp or positive integer.',
        self::VALUE_MUST_BE_TIME => 'Value must be a valid time.',
        self::VALUE_MUST_BE_DATE => 'Value must be a valid date.',
        self::VALUE_MUST_BE_STRING => 'String value expected.',
        self::VALUE_MUST_BE_PASSWORD_HASH => 'Value must be a password hash.',
        self::VALUE_MUST_BE_PLAIN_PASSWORD => 'Value must be a plain password (password hash detected).',
        self::VALUE_MUST_BE_ARRAY => 'Array expected.',
        self::VALUE_MUST_BE_RESOURCE => 'Resource value expected.',
        self::VALUE_MUST_BE_STRING_OR_RESOURCE => 'String or resource value expected.',
        self::VALUE_FROM_DB_CANNOT_BE_DB_EXPRESSION => 'Value received from DB cannot be instance of DbExpr.',
        self::VALUE_FROM_DB_CANNOT_BE_QUERY_BUILDER => 'Value received from DB cannot be instance of SelectQueryBuilderInterface.',
    ];

    public function getMessage(string $messageId): string
    {
        if (!isset($this->messages[$messageId])) {
            throw new \InvalidArgumentException(
                static::class . " has no translation for validation message with ID '{$messageId}'"
            );
        }
        return $this->messages[$messageId];
    }
}