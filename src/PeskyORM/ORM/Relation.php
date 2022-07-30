<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\JoinInfo;

class Relation
{
    
    public const HAS_ONE = 'has_one';
    public const HAS_MANY = 'has_many';
    public const BELONGS_TO = 'belongs_to';
    
    public const JOIN_LEFT = JoinInfo::JOIN_LEFT;
    public const JOIN_RIGHT = JoinInfo::JOIN_RIGHT;
    public const JOIN_INNER = JoinInfo::JOIN_INNER;
    
    /** @var string */
    protected $name;
    /** @var string */
    protected $type;
    /** @var string */
    protected $joinType = self::JOIN_LEFT;
    
    /** @var string */
    protected $localColumnName;
    
    /** @var TableInterface */
    protected $foreignTable;
    /** @var string */
    protected $foreignTableClass;
    /** @var string */
    protected $foreignColumnName;
    
    /** @var string|\Closure|null */
    protected $displayColumnName;
    
    /** @var array|\Closure */
    protected $additionalJoinConditions = [];
    
    /**
     * @param string $localColumnName
     * @param string $type
     * @param string|TableInterface $foreignTableClass
     * @param string $foreignColumnName
     * @return static
     * @throws \InvalidArgumentException
     */
    static public function create(
        string $localColumnName,
        string $type,
        $foreignTableClass,
        string $foreignColumnName
    ) {
        return new static($localColumnName, $type, $foreignTableClass, $foreignColumnName);
    }
    
    /**
     * @param string $localColumnName
     * @param string $type
     * @param string|TableInterface $foreignTableClass
     * @param string $foreignColumnName
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $localColumnName,
        string $type,
        $foreignTableClass,
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
        return !empty($this->name);
    }
    
    /**
     * @param string $name
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setName(string $name)
    {
        if ($this->hasName()) {
            throw new \BadMethodCallException('Relation name alteration is forbidden');
        } elseif (!preg_match(JoinInfo::NAME_VALIDATION_REGEXP, $name)) {
            throw new \InvalidArgumentException(
                "\$name argument contains invalid value: '$name'. Pattern: "
                . JoinInfo::NAME_VALIDATION_REGEXP . '. Example: CamelCase1'
            );
        }
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
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setType($type)
    {
        if (!is_string($type)) {
            throw new \InvalidArgumentException('$type argument must be a string');
        }
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
     * @param string $localColumnName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setLocalColumnName(string $localColumnName)
    {
        $this->localColumnName = $localColumnName;
        return $this;
    }
    
    public function getForeignTableClass(): string
    {
        return $this->foreignTableClass;
    }
    
    /**
     * @param string|TableInterface $foreignTableClass
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setForeignTableClass($foreignTableClass)
    {
        if ($foreignTableClass instanceof TableInterface) {
            $this->foreignTable = $foreignTableClass;
            $this->foreignTableClass = get_class($foreignTableClass);
        } else {
            if (!is_string($foreignTableClass)) {
                throw new \InvalidArgumentException('$foreignTableClass argument must be a string');
            }
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
     * @return TableInterface
     * @throws \BadMethodCallException
     */
    public function getForeignTable(): TableInterface
    {
        if (!$this->foreignTable) {
            if (!$this->foreignTableClass) {
                throw new \BadMethodCallException('You need to provide foreign table class via setForeignTableClass()');
            } else {
                /** @var TableInterface $foreignTableClass */
                $foreignTableClass = $this->foreignTableClass;
                $this->foreignTable = $foreignTableClass::getInstance();
                // note: it is already validated to implement TableInterface in setForeignTableClass()
            }
        }
        return $this->foreignTable;
    }
    
    /**
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getForeignColumnName(): string
    {
        if (
            $this->getType() === static::HAS_MANY
            && $this->getForeignTable()
                ->getPkColumnName() === $this->foreignColumnName
        ) {
            throw new \InvalidArgumentException(
                'Foreign column is a primary key column. It makes no sense for HAS MANY relation'
            );
        }
        return $this->foreignColumnName;
    }
    
    /**
     * @param string $foreignColumnName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setForeignColumnName(string $foreignColumnName)
    {
        $this->foreignColumnName = $foreignColumnName;
        return $this;
    }
    
    /**
     * @param TableInterface $localTable
     * @param null|string $localTableAlias
     * @param bool $forStandaloneSelect
     * @param Record|null $localRecord
     * @return array
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
        } else {
            return $this->additionalJoinConditions;
        }
    }
    
    /**
     * @param array|\Closure $additionalJoinConditions
     *      - \Closure: function (Relation $relation, TableInterface $localTable, string $localTableAlias, bool $forStandaloneSelect, ?Record $localRecord = null) { return []; }
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAdditionalJoinConditions($additionalJoinConditions)
    {
        if (!is_array($additionalJoinConditions) && !($additionalJoinConditions instanceof \Closure)) {
            throw new \InvalidArgumentException('$additionalJoinConditions argument must be an array or \Closure');
        }
        $this->additionalJoinConditions = $additionalJoinConditions;
        return $this;
    }
    
    /**
     * @return \Closure|string|null
     */
    public function getDisplayColumnName()
    {
        return $this->displayColumnName;
    }
    
    /**
     * @param string|\Closure $displayColumnName - function(array $relationData) { return $relationData['column']; };
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDisplayColumnName($displayColumnName)
    {
        if (!is_string($displayColumnName) && !($displayColumnName instanceof \Closure)) {
            throw new \InvalidArgumentException('$displayColumnName argument must be a string or closure');
        }
        $this->displayColumnName = $displayColumnName;
        return $this;
    }
    
    public function getJoinType(): string
    {
        return $this->joinType;
    }
    
    /**
     * @param string $joinType
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setJoinType(string $joinType)
    {
        $types = [static::JOIN_INNER, static::JOIN_LEFT, static::JOIN_RIGHT];
        if (!in_array($joinType, $types, true)) {
            throw new \InvalidArgumentException('$joinType argument must be one of: ' . implode(',', $types));
        }
        $this->joinType = $joinType;
        return $this;
    }
    
    /**
     * Convert to OrmJoinInfo
     * @param TableInterface $localTable
     * @param string|null $localTableAlias
     * @param string|null $joinName
     * @param string|null $joinType
     * @return OrmJoinInfo
     * @throws \InvalidArgumentException
     */
    public function toOrmJoinConfig(
        TableInterface $localTable,
        ?string $localTableAlias = null,
        ?string $joinName = null,
        ?string $joinType = null
    ): OrmJoinInfo {
        return OrmJoinInfo::create(
            $joinName ?: $this->getName(),
            $localTable,
            $this->getLocalColumnName(),
            $joinType ?: $this->getJoinType(),
            $this->getForeignTable(),
            $this->getForeignColumnName()
        )
            ->setAdditionalJoinConditions($this->getAdditionalJoinConditions($localTable, $localTableAlias, false))
            ->setTableAlias($localTableAlias ?: $localTable::getAlias());
    }
    
}
