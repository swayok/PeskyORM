<?php

declare(strict_types=1);

namespace PeskyORM\Join;

use PeskyORM\DbExpr;
use PeskyORM\Utils\ArgumentValidators;
use PeskyORM\Utils\DbAdapterMethodArgumentUtils;

abstract class NormalJoinConfigAbstract implements NormalJoinConfigInterface
{
    protected string $joinName;
    protected string $joinType;
    protected ?string $localTableAlias = null;
    protected ?string $localColumnName = null;
    protected ?string $foreignTableName = null;
    protected ?string $foreignTableSchema = null;
    protected ?string $foreignColumnName = null;
    protected array $additionalJoinConditions = [];
    protected array $foreignColumnsToSelect = ['*'];

    public function __construct(string $joinName, string $joinType)
    {
        $this->setJoinName($joinName)
            ->setJoinType($joinType);
    }

    public function getJoinName(): ?string
    {
        return $this->joinName;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function setJoinName(string $joinName): static
    {
        ArgumentValidators::assertNotEmpty('$joinName', $joinName);
        ArgumentValidators::assertPascalCase('$joinName', $joinName);
        $this->joinName = $joinName;
        return $this;
    }

    public function getJoinType(): ?string
    {
        return $this->joinType;
    }

    /**
     * @param string $joinType - one of self::JOIN_*
     * @throws \InvalidArgumentException
     */
    public function setJoinType(string $joinType): static
    {
        ArgumentValidators::assertNotEmpty('$joinType', $joinType);
        $joinType = strtolower($joinType);
        ArgumentValidators::assertInArray('$joinType', $joinType, $this->getJoinTypes());
        $this->joinType = $joinType;
        return $this;
    }

    protected function getJoinTypes(): array
    {
        return [self::JOIN_INNER, self::JOIN_LEFT, self::JOIN_RIGHT, self::JOIN_FULL];
    }

    public function getLocalTableAlias(): string
    {
        return $this->localTableAlias;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function setLocalTableAlias(string $localTableAlias): static
    {
        ArgumentValidators::assertValidDbEntityName('$localTableAlias', $localTableAlias);
        // we do not use ArgumentValidators::assertPascalCase() here to allow
        // targeting local table by name instead of alias
        $this->localTableAlias = $localTableAlias;
        return $this;
    }

    public function getLocalColumnName(): string
    {
        return $this->localColumnName;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function setLocalColumnName(string $columnName): static
    {
        ArgumentValidators::assertNotEmpty('$columnName', $columnName);
        $this->localColumnName = $columnName;
        return $this;
    }

    public function getForeignTableName(): string
    {
        return $this->foreignTableName;
    }

    public function getForeignColumnName(): string
    {
        return $this->foreignColumnName;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function setForeignColumnName(string $foreignColumnName): static
    {
        ArgumentValidators::assertNotEmpty('$foreignColumnName', $foreignColumnName);
        $this->foreignColumnName = $foreignColumnName;
        return $this;
    }

    public function getForeignTableSchema(): ?string
    {
        return $this->foreignTableSchema;
    }

    protected function getAdditionalJoinConditions(): array
    {
        return $this->additionalJoinConditions;
    }

    public function setAdditionalJoinConditions(array $conditions): static
    {
        $this->additionalJoinConditions = $conditions;
        return $this;
    }

    public function getForeignColumnsToSelect(): array
    {
        return $this->foreignColumnsToSelect;
    }

    public function setForeignColumnsToSelect(array $columns): static
    {
        DbAdapterMethodArgumentUtils::guardColumnsListArg($columns, true, true);
        $this->foreignColumnsToSelect = $columns;
        return $this;
    }

    public function getJoinConditions(): array
    {
        return array_merge(
            [
                $this->getJoinName() . '.' . $this->getForeignColumnName() => DbExpr::create(
                    "`{$this->getLocalTableAlias()}`.`{$this->getLocalColumnName()}`",
                    false
                )
            ],
            $this->getAdditionalJoinConditions()
        );
    }

    public function isValid(): bool
    {
        return (
            $this->localTableAlias
            && $this->localColumnName
            && $this->foreignTableName
            && $this->foreignColumnName
            && $this->joinType
            && $this->joinName
        );
    }

}
