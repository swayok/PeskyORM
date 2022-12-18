<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesEn;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Utils\ArgumentValidators;

abstract class TableColumnAbstract implements TableColumnInterface
{
    protected const AFTER_SAVE_PAYLOAD_KEY = '_for_after_save';

    protected ?string $name = null;
    protected ?TableStructureInterface $tableStructure = null;

    protected array $formatters = [];
    protected string $columnNameWithFormatGlue = '_as_';

    protected ?ColumnValueValidationMessagesInterface $valueValidationMessages = null;

    public function __construct(string $name)
    {
        $this->setName($name);
        $this->registerDefaultValueFormatters();
    }

    /**
     * @throws \UnexpectedValueException
     */
    public function getName(): string
    {
        if (empty($this->name)) {
            throw new \UnexpectedValueException('DB column name is not provided');
        }
        return $this->name;
    }

    public function hasName(): bool
    {
        return !empty($this->name);
    }

    /**
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function setName(string $name): static
    {
        ArgumentValidators::assertNotEmpty('$name', $name);
        ArgumentValidators::assertSnakeCase('$name', $name);

        $this->name = $name;
        return $this;
    }

    /**
     * Register default formatters for this Column
     * @see self::addValueFormatter()
     * @see ColumnValueFormatters
     */
    protected function registerDefaultValueFormatters(): void
    {
        // use $this->addValueFormatter($name, )
    }

    /**
     * Add a value formatter.
     * Example: formatter name is 'timestamp'.
     * If column name is 'datetime' then you can use
     * RecordInterface->datetime_as_timestamp to get formatted value.
     * Formatter \Closure signature:
     * function(RecordValueContainerInterface $valueContainer): mixed;
     * @see RecordInterface::getValue()
     */
    public function addValueFormatter(string $name, \Closure $formatter): static
    {
        $this->formatters[$name] = $formatter;
        return $this;
    }

    /**
     * @throws TableColumnConfigException
     */
    protected function getValueFormatter(string $name): \Closure
    {
        if (!isset($this->formatters[$name])) {
            throw new TableColumnConfigException(
                "There is no formatter '{$name}' for column {$this->getNameForException()}",
                $this
            );
        }
        return $this->formatters[$name];
    }

    protected function getFormattedValue(
        RecordValueContainerInterface $valueContainer,
        string $format
    ): mixed {
        $formatter = $this->getValueFormatter($format);
        return $formatter($valueContainer);
    }

    public function getValueFormatersNames(): array
    {
        $name = $this->getName();
        $ret = [];
        // List column name alias for each value format.
        // Should look like: 'name_as_timestamp', 'name_as_date', ...
        $glue = $this->columnNameWithFormatGlue;
        foreach ($this->formatters as $format => $formatter) {
            $ret[$name . $glue . $format] = $format;
        }
        return $ret;
    }

    public function getTableStructure(): ?TableStructureInterface
    {
        return $this->tableStructure;
    }

    public function setTableStructure(TableStructureInterface $tableStructure): static
    {
        $this->tableStructure = $tableStructure;
        return $this;
    }

    protected function getNameForException(?TableColumnInterface $column = null): string
    {
        if (!$column) {
            $column = $this;
        }
        $tableStructure = $column->getTableStructure();
        if ($tableStructure) {
            return get_class($tableStructure) . '->' . $column->getName();
        }
        return static::class . "('{$column->getName()}')";
    }

    public function isNullableValues(): bool
    {
        return false;
    }

    public function isPrimaryKey(): bool
    {
        return false;
    }

    public function isValueMustBeUnique(): bool
    {
        return false;
    }

    public function isHeavyValues(): bool
    {
        return false;
    }

    public function isFile(): bool
    {
        return false;
    }

    public function isPrivateValues(): bool
    {
        return false;
    }

    protected function getValueValidationMessages(): ColumnValueValidationMessagesInterface
    {
        if (!$this->valueValidationMessages) {
            // todo: get ColumnValueValidationMessagesInterface instance from classes container
            $this->valueValidationMessages = new ColumnValueValidationMessagesEn();
        }
        return $this->valueValidationMessages;
    }

    protected function getValueValidationMessage(string $messageId): string
    {
        return $this->getValueValidationMessages()->getMessage($messageId);
    }

    public function getNewRecordValueContainer(
        RecordInterface $record
    ): RecordValueContainerInterface {
        // todo: get RecordValueContainerInterface class from classes container
        return new RecordValue($this, $record);
    }

    public function afterSave(
        RecordValueContainerInterface $valueContainer,
        bool $isUpdate,
    ): void {
        $valueContainer->pullPayload(static::AFTER_SAVE_PAYLOAD_KEY);
    }

    public function afterDelete(
        RecordValueContainerInterface $valueContainer,
        bool $shouldDeleteFiles
    ): void {
    }

    protected function getRecordInfoForException(
        RecordValueContainerInterface $valueContainer
    ): string {
        $record = $valueContainer->getRecord();
        $pk = 'undefined';
        if (!$this->isPrimaryKey()) {
            try {
                $pk = $record->existsInDb()
                    ? $record->getPrimaryKeyValue()
                    : 'null';
            } catch (\Throwable) {
            }
        }
        return get_class($record) . '(#' . $pk . ')->' . $this->getName();
    }
}