<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\Join\JoinConfigInterface;
use PeskyORM\Join\OrmJoinConfig;
use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Table\TableInterface;
use PeskyORM\Utils\ArgumentValidators;

class Relation
{

    public const HAS_ONE = 'has_one';
    public const HAS_MANY = 'has_many';
    public const BELONGS_TO = 'belongs_to';

    public const JOIN_LEFT = JoinConfigInterface::JOIN_LEFT;
    public const JOIN_RIGHT = JoinConfigInterface::JOIN_RIGHT;
    public const JOIN_INNER = JoinConfigInterface::JOIN_INNER;
    public const JOIN_FULL = JoinConfigInterface::JOIN_FULL;

    protected ?string $name = null;
    protected string $type;
    protected string $joinType = self::JOIN_LEFT;

    protected string $localColumnName;

    protected ?TableInterface $foreignTable = null;
    protected string $foreignTableClass;
    protected string $foreignColumnName;

    protected string|\Closure|null $displayColumnName = null;

    protected \Closure|array $additionalJoinConditions = [];

    public static function create(
        string $localColumnName,
        string $type,
        TableInterface|string $foreignTableClass,
        string $foreignColumnName
    ): static {
        return new static($localColumnName, $type, $foreignTableClass, $foreignColumnName);
    }

    public function __construct(
        string $localColumnName,
        string $type,
        TableInterface|string $foreignTableClass,
        string $foreignColumnName
    ) {
        $this
            ->setLocalColumnName($localColumnName)
            ->setDisplayColumnName($foreignColumnName)
            ->setType($type)
            ->setForeignTableClass($foreignTableClass)
            ->setForeignColumnName($foreignColumnName);
    }

    public function hasName(): bool
    {
        return (bool)$this->name;
    }

    /**
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setName(string $name): static
    {
        if ($this->hasName()) {
            throw new \BadMethodCallException('Relation name alteration is forbidden');
        }

        ArgumentValidators::assertNotEmpty('$name', $name);
        ArgumentValidators::assertPascalCase('$name', $name);

        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        if (empty($this->name)) {
            throw new \UnexpectedValueException('Relation name is not provided');
        }
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setType(string $type): static
    {
        $types = [static::BELONGS_TO, static::HAS_MANY, static::HAS_ONE];
        if (!in_array($type, $types, true)) {
            throw new \InvalidArgumentException('$type argument must be one of: ' . implode(',', $types));
        }
        $this->type = $type;
        return $this;
    }

    public function getLocalColumnName(): string
    {
        return $this->localColumnName;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setLocalColumnName(string $localColumnName): static
    {
        $this->localColumnName = $localColumnName;
        return $this;
    }

    public function getForeignTableClass(): string
    {
        return $this->foreignTableClass;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setForeignTableClass(TableInterface|string $foreignTableClass): static
    {
        if ($foreignTableClass instanceof TableInterface) {
            $this->foreignTable = $foreignTableClass;
            $this->foreignTableClass = get_class($foreignTableClass);
        } else {
            /** @var string $foreignTableClass */
            if (!class_exists($foreignTableClass)) {
                throw new \InvalidArgumentException(
                    "\$foreignTableClass argument contains invalid value: class '$foreignTableClass' does not exist"
                );
            }
            if (!is_subclass_of($foreignTableClass, TableInterface::class)) {
                throw new \InvalidArgumentException(
                    "\$foreignTableClass $foreignTableClass must implement " . TableInterface::class . ' interface'
                );
            }
            $this->foreignTableClass = $foreignTableClass;
        }
        return $this;
    }

    /**
     * @throws \BadMethodCallException
     */
    public function getForeignTable(): TableInterface
    {
        if (!$this->foreignTable) {
            if (!$this->foreignTableClass) {
                throw new \BadMethodCallException('You need to provide foreign table class via setForeignTableClass()');
            }

            /** @var TableInterface $foreignTableClass */
            $foreignTableClass = $this->foreignTableClass;
            $this->foreignTable = $foreignTableClass::getInstance();
            // note: it is already validated to implement TableInterface in setForeignTableClass()
        }
        return $this->foreignTable;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getForeignColumnName(): string
    {
        if (
            $this->getType() === static::HAS_MANY
            && $this->getForeignTable()->getPkColumnName() === $this->foreignColumnName
        ) {
            throw new \InvalidArgumentException(
                'Foreign column is a primary key column. It makes no sense for HAS MANY relation'
            );
        }

        if (!$this->getForeignTable()->getTableStructure()->hasColumn($this->foreignColumnName)) {
            throw new \InvalidArgumentException(
                "Related table {$this->getForeignTableClass()} has no column '{$this->foreignColumnName}'. Relation: " . $this->getName()
            );
        }

        return $this->foreignColumnName;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setForeignColumnName(string $foreignColumnName): static
    {
        $this->foreignColumnName = $foreignColumnName;
        return $this;
    }

    /**
     * @throws \UnexpectedValueException
     */
    public function getAdditionalJoinConditions(
        TableInterface $localTable,
        ?string $localTableAlias,
        bool $forStandaloneSelect,
        ?Record $localRecord = null
    ): array {
        if ($this->additionalJoinConditions instanceof \Closure) {
            $conditions = call_user_func(
                $this->additionalJoinConditions,
                $this,
                $localTable,
                $localTableAlias ?: $localTable::getAlias(),
                $forStandaloneSelect,
                $localRecord
            );
            if (!is_array($conditions)) {
                throw new \UnexpectedValueException(
                    'Relation->additionalJoinConditions closure must return array. '
                    . gettype($conditions) . ' received. Relation name: ' . $this->getName()
                );
            }
            return $conditions;
        }

        return $this->additionalJoinConditions;
    }

    /**
     * \Closure => function (Relation $relation, TableInterface $localTable, string $localTableAlias, bool $forStandaloneSelect, ?Record $localRecord = null): array { return []; }
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
        $this->displayColumnName = $displayColumnName;
        return $this;
    }

    public function getJoinType(): string
    {
        return $this->joinType;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setJoinType(string $joinType): static
    {
        $types = $this->getJoinTypes();
        if (!in_array($joinType, $types, true)) {
            throw new \InvalidArgumentException('$joinType argument must be one of: ' . implode(',', $types));
        }
        $this->joinType = $joinType;
        return $this;
    }

    protected function getJoinTypes(): array
    {
        return [static::JOIN_INNER, static::JOIN_LEFT, static::JOIN_RIGHT, static::JOIN_FULL];
    }

    public function toOrmJoinConfig(
        TableInterface $localTable,
        ?string $localTableAlias = null,
        ?string $joinName = null,
        ?string $joinType = null
    ): OrmJoinConfig {
        $ormJoin = new OrmJoinConfig(
            $joinName ?: $this->getName(),
            $localTable,
            $this->getLocalColumnName(),
            $joinType ?: $this->getJoinType(),
            $this->getForeignTable(),
            $this->getForeignColumnName()
        );
        $ormJoin
            ->setAdditionalJoinConditions($this->getAdditionalJoinConditions($localTable, $localTableAlias, false))
            ->setTableAlias($localTableAlias ?: $localTable::getAlias());
        return $ormJoin;
    }

}
