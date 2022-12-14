<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\VirtualTableColumnAbstract;

/**
 * This column can access values from other columns or generate independent values.
 * Example: generate a value based on values of several other columns.
 * For more complex situations you should create specific classes.
 */
class VirtualColumn extends VirtualTableColumnAbstract
{
    protected \Closure $valueChecker;
    protected \Closure $valueGetter;

    /**
     * $hasValue signature:
     *   function (RecordValueContainerInterface $valueContainer, bool $allowDefaultValue): bool
     * $getValue signature:
     *   function (RecordValueContainerInterface $valueContainer, ?string $format): mixed
     * Note: $hasValue will be called before $getValue, so you do not need
     * to call $hasValue again in $getValue
     */
    public function __construct(
        string $name,
        \Closure $hasValue,
        \Closure $getValue
    ) {
        $this->valueChecker = $hasValue;
        $this->valueGetter = $getValue;
        parent::__construct($name);
    }

    public function getValue(
        RecordValueContainerInterface $valueContainer,
        ?string $format
    ): mixed {
        $record = $valueContainer->getRecord();
        if (!$this->hasValue($valueContainer, !$record->existsInDb())) {
            $columnInfo = $this->getRecordInfoForException($valueContainer);
            throw new \BadMethodCallException(
                "Value for virtual column {$columnInfo} cannot be generated."
            );
        }
        return call_user_func(
            $this->valueGetter,
            $valueContainer,
            $format
        );
    }

    public function hasValue(
        RecordValueContainerInterface $valueContainer,
        bool $allowDefaultValue
    ): bool {
        return (bool)call_user_func(
            $this->valueChecker,
            $valueContainer,
            $allowDefaultValue
        );
    }
}