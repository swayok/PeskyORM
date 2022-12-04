<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;

interface UniqueTableColumnInterface
{
    /**
     * Values in column are unique within self::getUniqueContraintAdditonalColumns().
     * @see CanBeUnique for implementation.
     */
    public function isValueMustBeUnique(): bool;

    /**
     * Should return true if uniqueness validation should perform
     * case sensitive validation.
     * Default: true
     * Case sensitive validation is faster
     */
    public function isUniqueContraintCaseSensitive(): bool;

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
    public function getUniqueContraintAdditonalColumns(): array;
}