<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

trait CanBeUnique
{
    protected bool $isValueMustBeUnique = false;
    /**
     * Should value uniqueness be case-sensitive or not?
     */
    protected bool $isUniqueContraintCaseSensitive = true;
    /**
     * Other columns used in uniqueness constraint (multi-column uniqueness)
     */
    protected array $uniqueContraintAdditonalColumns = [];

    public function isValueMustBeUnique(): bool
    {
        return $this->isValueMustBeUnique;
    }

    public function isUniqueContraintCaseSensitive(): bool
    {
        return $this->isUniqueContraintCaseSensitive;
    }

    public function getUniqueContraintAdditonalColumns(): array
    {
        return $this->uniqueContraintAdditonalColumns;
    }

    /**
     * Note: there is no automatic uniqueness validation in DefaultColumnClosures class!
     * @param bool $caseSensitive - true: compare values as is; false: compare lowercased values (emails for example);
     *      Note that case-insensitive mode uses more resources than case-sensitive!
     * @param array $withinColumns - used to provide list of columns for cases when uniqueness constraint in DB
     *      uses 2 or more columns.
     *      For example: when 'title' column must be unique within 'category' (category_id column)
     */
    public function uniqueValues(bool $caseSensitive = true, ...$withinColumns): static
    {
        $this->isValueMustBeUnique = true;
        $this->isUniqueContraintCaseSensitive = $caseSensitive;
        if (
            count($withinColumns) === 1
            && isset($withinColumns[0])
            && is_array($withinColumns[0])
        ) {
            $this->uniqueContraintAdditonalColumns = $withinColumns[0];
        } else {
            $this->uniqueContraintAdditonalColumns = $withinColumns;
        }
        return $this;
    }
}