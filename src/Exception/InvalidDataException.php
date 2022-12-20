<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\Utils\ServiceContainer;
use Swayok\Utils\Set;

class InvalidDataException extends OrmException
{
    protected array $errors = [];

    public function __construct(
        array $errors,
        protected RecordInterface $record,
        protected ?TableColumnInterface $column = null,
        protected mixed $invalidValue = null
    ) {
        $this->errors = $errors;
        parent::__construct(
            $this->makeExceptionMessage(),
            static::CODE_INVALID_DATA
        );
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
        foreach ($errors as $key => &$error) {
            $prefix = '';
            if (!is_int($key)) {
                $prefix = '[' . $key . '] ';
            }
            if (is_array($error)) {
                $error = implode(', ', Set::flatten($error));
            }
            $error = $prefix . rtrim($error, '.');
        }
        unset($error);
        return $errors;
    }

    /**
     * Flattened errors has only 1 level of nesting (basically key-value array).
     * Keys - error path: 'field', 'group.field'.
     * Values - messages array.
     * @see self::flattenErrors()
     */
    public function getErrors(bool $flatten = true): array
    {
        if ($flatten) {
            return $this->flattenErrors();
        }
        return $this->errors;
    }

    /**
     * Converts nested array (up to 2 levels):
     * [
     *      'field' => ['error message 1'],
     *      'group' => [
     *          'field1' => ['error message 2'],
     *          'field2' => ['error message 3'],
     *      ],
     * ]
     * to array with 1 level of nesting:
     * [
     *      'field' => ['error message 1']
     *      'group.field1' => ['error message 2']
     *      'group.field2' => ['error message 3']
     * ]
     */
    protected function flattenErrors(): array
    {
        $flatErrors = [];
        foreach ($this->errors as $columnName => $errors) {
            if (isset($errors[0])) {
                $flatErrors[$columnName] = $errors;
            } else {
                foreach ($errors as $subKey => $realErrors) {
                    $flatErrors[$columnName . '.' . $subKey] = $realErrors;
                }
            }
        }
        return $flatErrors;
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