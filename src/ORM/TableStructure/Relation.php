<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\Join\JoinConfigInterface;
use PeskyORM\Join\OrmJoinConfig;
use PeskyORM\Join\OrmJoinConfigInterface;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Table\TableInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\Utils\ArgumentValidators;

class Relation implements RelationInterface
{
    protected ?string $name = null;
    protected string $type;
    protected string $joinType = JoinConfigInterface::JOIN_LEFT;

    protected string $localColumnName;

    protected TableInterface $foreignTable;
    protected TableColumnInterface $foreignColumn;

    protected string|\Closure|null $displayColumnName = null;
    protected \Closure|array $additionalJoinConditions = [];

    public function __construct(
        string $localColumnName,
        string $relationType,
        TableInterface $foreignTable,
        string $foreignColumnName,
        ?string $name = null
    ) {
        $this
            ->setLocalColumnName($localColumnName)
            ->setDisplayColumnName($foreignColumnName)
            ->setType($relationType)
            ->setForeignTable($foreignTable)
            ->setForeignColumnName($foreignColumnName);
        if ($name) {
            $this->setName($name);
        }
    }

    public function getJoinType(): string
    {
        return $this->joinType;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setName(string $relationName): static
    {
        ArgumentValidators::assertNotEmpty('$relationName', $relationName);
        ArgumentValidators::assertPascalCase('$relationName', $relationName);
        $this->name = $relationName;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function setType(string $relationType): static
    {
        ArgumentValidators::assertInArray(
            '$relationType',
            $relationType,
            [static::BELONGS_TO, static::HAS_MANY, static::HAS_ONE]
        );
        $this->type = $relationType;
        return $this;
    }

    public function getLocalColumnName(): string
    {
        return $this->localColumnName;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function setLocalColumnName(string $localColumnName): static
    {
        ArgumentValidators::assertNotEmpty('$localColumnName', $localColumnName);
        $this->localColumnName = $localColumnName;
        return $this;
    }

    protected function setForeignTable(TableInterface $foreignTable): static
    {
        $this->foreignTable = $foreignTable;
        return $this;
    }

    public function getForeignTable(): TableInterface
    {
        return $this->foreignTable;
    }

    public function getForeignColumnName(): string
    {
        return $this->foreignColumn->getName();
    }

    public function getForeignColumn(): TableColumnInterface
    {
        return $this->foreignColumn;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function setForeignColumnName(string $foreignColumnName): static
    {
        ArgumentValidators::assertNotEmpty('$foreignColumnName', $foreignColumnName);
        // check if column exists in foreign table
        $foreignColumn = $this->getForeignTable()
            ->getTableStructure()
            ::getColumn($foreignColumnName);
        // Check if foreign column name is not a primary key in HAS MANY relation.
        // Otherwise, it is a mistake.
        if ($this->getType() === static::HAS_MANY && $foreignColumn->isPrimaryKey()) {
            throw new \InvalidArgumentException(
                "\$foreignColumnName argument value ('{$foreignColumnName}') refers to"
                . " a primary key column. It makes no sense for HAS MANY relation."
            );
        }

        $this->foreignColumn = $foreignColumn;
        return $this;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getAdditionalJoinConditions(
        bool $forStandaloneSelect,
        string $localTableAlias,
        ?RecordInterface $localRecord = null
    ): array {
        if ($this->additionalJoinConditions instanceof \Closure) {
            $conditions = call_user_func(
                $this->additionalJoinConditions,
                $this,
                $localTableAlias,
                $forStandaloneSelect,
                $localRecord
            );
            if (!is_array($conditions)) {
                throw new \InvalidArgumentException(
                    '$additionalJoinConditions closure must return array, but '
                    . gettype($conditions) . ' received. Relation name: ' . $this->getName()
                );
            }
            return $conditions;
        }

        return $this->additionalJoinConditions;
    }

    /**
     * \Closure => function (
     *      Relation $relation,
     *      string $localTableAlias,
     *      bool $forStandaloneSelect,
     *      ?Record $localRecord = null
     * ): array
     */
    public function setAdditionalJoinConditions(array|\Closure $additionalJoinConditions): static
    {
        $this->additionalJoinConditions = $additionalJoinConditions;
        return $this;
    }

    public function getDisplayColumnName(): string|\Closure|null
    {
        return $this->displayColumnName;
    }

    /**
     * \Closure => function(array $relationData): string { return $relationData['column']; };
     */
    public function setDisplayColumnName(\Closure|string $displayColumnName): static
    {
        ArgumentValidators::assertNotEmptyString('$displayColumnName', $displayColumnName, true);
        $this->displayColumnName = $displayColumnName;
        return $this;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setJoinType(string $joinType): static
    {
        ArgumentValidators::assertInArray('$joinType', $joinType, $this->getAllowedJoinTypes());
        $this->joinType = $joinType;
        return $this;
    }

    protected function getAllowedJoinTypes(): array
    {
        return [
            JoinConfigInterface::JOIN_INNER,
            JoinConfigInterface::JOIN_LEFT,
            JoinConfigInterface::JOIN_RIGHT,
            JoinConfigInterface::JOIN_FULL,
        ];
    }

    public function toJoinConfig(
        string $localTableAlias,
        ?string $overrideJoinName = null,
        ?string $overrideJoinType = null
    ): OrmJoinConfigInterface {
        $ormJoin = new OrmJoinConfig(
            $overrideJoinName ?? $this->getName(),
            $overrideJoinType ?? $this->getJoinType(),
            $localTableAlias,
            $this->getLocalColumnName(),
            $this->getForeignTable(),
            $this->getForeignColumnName()
        );
        $ormJoin->setAdditionalJoinConditions(
            $this->getAdditionalJoinConditions(false, $localTableAlias)
        );
        return $ormJoin;
    }

}
