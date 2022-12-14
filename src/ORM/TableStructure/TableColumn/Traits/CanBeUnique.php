<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

/**
 * Note: there is no automatic uniqueness validation in ORM!
 * @see TableColumnInterface::isValueMustBeUnique()
 */
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

    /**
     * @see TableColumnInterface::isValueMustBeUnique()
     */
    public function isValueMustBeUnique(): bool
    {
        return $this->isValueMustBeUnique;
    }

    /**
     * Should return true if uniqueness validation should perform
     * case sensitive validation.
     * Default: true
     * Case sensitive validation is faster
     */
    public function isUniqueContraintCaseSensitive(): bool
    {
        return $this->isUniqueContraintCaseSensitive;
    }

    /**
     * Return secondary uniqueness columns if uniqueness constraint contains several columns.
     * You should return true from isValueMustBeUnique() only for the main (first) column
     * in constraint.
     * Secondary columns should return false from isValueMustBeUnique().
     * Example: Unique constrant: ("bill", "type").
     * "bill" is main column, it returns true from isValueMustBeUnique() and
     * ["type"] from getUniqueContraintAdditonalColumns().
     * "type" is secondary column, it returns false from isValueMustBeUnique()
     * and empty array from getUniqueContraintAdditonalColumns().
     */
    public function getUniqueContraintAdditonalColumns(): array
    {
        return $this->uniqueContraintAdditonalColumns;
    }

    /**
     *
     * This configs should be used in your code to check uniqueness.
     * @param bool $caseSensitive
     *      - true: compare values as is;
     *      - false: compare values ignoring case sensitivity (for example: emails and logins).
     *      Note that case-insensitive mode uses more resources than case-sensitive!
     * @param array $withinColumns - used to provide list of columns for cases when
     *      uniqueness constraint in DB uses 2 or more columns.
     *      Example: 'title' column must be unique within 'category' ("category_id" column)
     * @see self::getUniqueContraintAdditonalColumns()
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