<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\JoinInfo;

class Relation {

    const HAS_ONE = 'has_one';
    const HAS_MANY = 'has_many';
    const BELONGS_TO = 'belongs_to';

    const JOIN_LEFT = JoinInfo::JOIN_LEFT;
    const JOIN_RIGHT = JoinInfo::JOIN_RIGHT;
    const JOIN_INNER = JoinInfo::JOIN_INNER;

    /** @var string */
    protected $name;
    /** @var string */
    protected $type;
    /** @var  */
    protected $joinType = self::JOIN_LEFT;

    /** @var string */
    protected $localColumnName;

    /** @var TableInterface */
    protected $foreignTable;
    /** @var string */
    protected $foreignTableClass;
    /** @var string */
    protected $foreignColumnName;

    /** @var string */
    protected $displayColumnName;

    /** @var array */
    protected $additionalJoinConditions = [];

    /**
     * @param string $localColumnName
     * @param string $type
     * @param string $foreignTableClass
     * @param string $foreignColumnName
     * @return static
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function create(
        $localColumnName,
        $type,
        $foreignTableClass,
        $foreignColumnName
    ) {
        return new static($localColumnName, $type, $foreignTableClass, $foreignColumnName);
    }

    /**
     * @param string $localColumnName
     * @param string $type
     * @param string $foreignTableClass
     * @param string $foreignColumnName
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     */
    public function __construct(
        $localColumnName,
        $type,
        $foreignTableClass,
        $foreignColumnName
    ) {
        $this
            ->setLocalColumnName($localColumnName)
            ->setDisplayColumnName($localColumnName)
            ->setType($type)
            ->setForeignTableClass($foreignTableClass)
            ->setForeignColumnName($foreignColumnName)
            ;
    }

    /**
     * @return bool
     */
    public function hasName() {
        return !empty($this->name);
    }

    /**
     * @param string $name
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setName($name) {
        if ($this->hasName()) {
            throw new \BadMethodCallException('Relation name alteration is forbidden');
        } else if (!preg_match(JoinInfo::NAME_VALIDATION_REGEXP, $name)) {
            throw new \InvalidArgumentException(
                "\$name argument contains invalid value: '$name'. Pattern: "
                    . JoinInfo::NAME_VALIDATION_REGEXP . '. Example: CamelCase1'
            );
        }
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getName() {
        if (empty($this->name)) {
            throw new \UnexpectedValueException('Relation name is not provided');
        }
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setType($type) {
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

    /**
     * @return string
     */
    public function getLocalColumnName() {
        return $this->localColumnName;
    }

    /**
     * @param string $localColumnName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setLocalColumnName($localColumnName) {
        if (!is_string($localColumnName)) {
            throw new \InvalidArgumentException('$localColumnName argument must be a string');
        }
        $this->localColumnName = $localColumnName;
        return $this;
    }

    /**
     * @return string
     */
    public function getForeignTableClass() {
        return $this->foreignTableClass;
    }

    /**
     * @param string $foreignTableClass
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function setForeignTableClass($foreignTableClass) {
        if (!is_string($foreignTableClass)) {
            throw new \InvalidArgumentException('$foreignTableClass argument must be a string');
        }
        if (!class_exists($foreignTableClass)) {
            throw new \InvalidArgumentException(
                "\$foreignTableClass argument contains invalid value: class '$foreignTableClass' does not exist"
            );
        }
        $this->foreignTableClass = $foreignTableClass;
        /** @var TableInterface $foreignTableClass */
        $this->foreignTable = $foreignTableClass::getInstance();
        return $this;
    }

    /**
     * @return TableInterface
     * @throws \BadMethodCallException
     */
    public function getForeignTable() {
        if (!$this->foreignTable) {
            throw new \BadMethodCallException('You need to provide foreign table class via setForeignTableClass()');
        }
        return $this->foreignTable;
    }

    /**
     * @return string
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function getForeignColumnName() {
        if (
            $this->getType() === static::HAS_MANY
            && $this->getForeignTable()->getPkColumnName() === $this->foreignColumnName
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
    public function setForeignColumnName($foreignColumnName) {
        if (!is_string($foreignColumnName)) {
            throw new \InvalidArgumentException('$foreignColumnName argument must be a string');
        }
        $this->foreignColumnName = $foreignColumnName;
        return $this;
    }

    /**
     * @return array
     */
    public function getAdditionalJoinConditions() {
        return $this->additionalJoinConditions;
    }

    /**
     * @param array $additionalJoinConditions
     * @return $this
     */
    public function setAdditionalJoinConditions(array $additionalJoinConditions) {
        $this->additionalJoinConditions = $additionalJoinConditions;
        return $this;
    }

    /**
     * @return string
     */
    public function getDisplayColumnName() {
        return $this->displayColumnName;
    }

    /**
     * @param string $displayColumnName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDisplayColumnName($displayColumnName) {
        if (!is_string($displayColumnName)) {
            throw new \InvalidArgumentException('$displayColumnName argument must be a string');
        }
        $this->displayColumnName = $displayColumnName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getJoinType() {
        return $this->joinType;
    }

    /**
     * @param mixed $joinType
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setJoinType($joinType) {
        if (!is_string($joinType)) {
            throw new \InvalidArgumentException('$joinType argument must be a string');
        }
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
     * @return OrmJoinInfo
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     */
    public function toOrmJoinConfig(TableInterface $localTable, $localTableAlias = null, $joinName = null) {
        return OrmJoinInfo::create($joinName ?: $this->getName())
            ->setConfigForLocalTable($localTable, $this->getLocalColumnName())
            ->setConfigForForeignTable($this->getForeignTable(), $this->getForeignColumnName())
            ->setJoinType($this->getJoinType())
            ->setAdditionalJoinConditions($this->getAdditionalJoinConditions())
            ->setTableAlias($localTableAlias ?: $localTable::getAlias());
    }

}