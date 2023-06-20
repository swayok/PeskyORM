<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages;

class ColumnValueValidationMessagesFromArray extends ColumnValueValidationMessagesEn
{
    public function __construct(array $translations)
    {
        $this->messages = array_merge($this->messages, $translations);
    }

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
