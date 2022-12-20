<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\Utils\ServiceContainer;

class InvalidDataException extends OrmException
{
    protected array $errors = [];

    /**
     * $errors expected to be a 1-dimesional associative array like: [
     *      'key' => ['error1', 'error2', ...],
     *      'group.key' => ['error1', 'error2', ...],
     *      'group.subgroup.key' => ['error1', 'error2', ...],
     * ]
     * Key: name of column (if column works with arrays - key may be complex).
     * Value: list of error messages (indexed array)
     */
    public function __construct(
        array $errors,
        protected RecordInterface $record,
        protected ?TableColumnInterface $column = null,
        protected mixed $invalidValue = null
    ) {
        $this->errors = $this->normalizeErrors($errors);
        parent::__construct(
            $this->makeExceptionMessage(),
            static::CODE_INVALID_DATA
        );
    }

    protected function normalizeErrors(array $errors): array
    {
        foreach ($errors as &$messages) {
            if (!is_array($messages)) {
                $messages = [$messages];
            }
        }
        unset($messages);
        return $errors;
    }

    protected function makeExceptionMessage(): string
    {
        /** @var ColumnValueValidationMessagesInterface $messagesContainer */
        $messagesContainer = ServiceContainer::getInstance()
            ->make(ColumnValueValidationMessagesInterface::class);
        return sprintf(
            $messagesContainer->getMessage(
                ColumnValueValidationMessagesInterface::EXCEPTION_MESSAGE
            ),
            implode('; ', $this->normalizeErrorsForMessage($this->errors))
        );
    }

    protected function normalizeErrorsForMessage(array $errors): array
    {
        $ret = [];
        foreach ($errors as $key => $messages) {
            foreach ($messages as &$message) {
                $message = rtrim($message, '.;,');
            }
            unset($message);
            $ret[] = '[' . $key . '] ' . implode(', ', $messages);
        }
        return $ret;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getRecord(): RecordInterface
    {
        return $this->record;
    }

    public function getColumn(): ?TableColumnInterface
    {
        return $this->column;
    }

    public function getInvalidValue(): mixed
    {
        return $this->invalidValue;
    }
}