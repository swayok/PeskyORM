<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages;

class ColumnValueValidationMessagesEn implements ColumnValueValidationMessagesInterface
{
    protected array $messages = [
        self::VALUE_CANNOT_BE_NULL => 'Null value is not allowed.',
        self::VALUE_MUST_BE_BOOLEAN => 'Value must be of a boolean data type.',
        self::VALUE_MUST_BE_INTEGER => 'Value must be of an integer data type.',
        self::VALUE_MUST_BE_FLOAT => 'Value must be of a numeric data type.',
        self::VALUE_MUST_BE_IMAGE => 'Value must be an uploaded image info.',
        self::VALUE_MUST_BE_FILE => 'Value must be an uploaded file info.',
        self::VALUE_MUST_BE_JSON => 'Value must be a json-encoded string or array.',
        self::VALUE_MUST_BE_IPV4_ADDRESS => 'Value must be an IPv4 address.',
        self::VALUE_MUST_BE_EMAIL => 'Value must be an email.',
        self::VALUE_MUST_BE_TIMEZONE_OFFSET => 'Value must be a valid timezone offset.',
        self::VALUE_MUST_BE_TIMESTAMP => 'Value must be a valid timestamp.',
        self::VALUE_MUST_BE_TIMESTAMP_WITH_TZ => 'Value must be a valid timestamp with time zone.',
        self::VALUE_MUST_BE_TIME => 'Value must be a valid time.',
        self::VALUE_MUST_BE_DATE => 'Value must be a valid date.',
        self::VALUE_MUST_BE_STRING => 'Value must be a string.',
        self::VALUE_MUST_BE_ARRAY => 'Value must be an array.',
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