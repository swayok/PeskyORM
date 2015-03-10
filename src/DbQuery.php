<?php

namespace PeskyORM;

use PeskyORM\Exception\DbQueryException;

class DbQuery {

    /** @var Db */
    protected $db;
    /** @var string */
    protected $schema = 'public';
    /** @var string */
    protected $table = '__unknown__';
    /** @var string */
    protected $alias = '__Table';
    /** @var array */
    protected $fields = array();
    /** @var bool */
    protected $distinct = false;
    /** @var array */
    protected $joins = array();
    /** @var string|array|null */
    protected $where = '';
    /** @var array */
    protected $orderBy = array();
    /** @var array */
    protected $groupBy = array();
    /** @var string|int */
    protected $limit;
    /** @var int */
    protected $offset = 0;
    /** @var string */
    protected $having = '';
    /** @var string */
    public $query = '';

    /* service */
    /** @var array */
    protected $aliasToTable = array();
    /** @var DbModel[] */
    protected $models = array();

    static public function create(DbModel $model, $alias = null) {
        return new DbQuery($model, $alias);
    }

    public function __construct(DbModel $model, $alias = null) {
        $this->db = $model->getDataSource();
        $this->schema = $model->schema;
        $this->table = "{$model->schema}.{$model->table}";
        $this->models[$this->table] = $model;
        if (empty($alias)) {
            $this->alias = !empty($model->alias)
                ? $model->alias
                : $this->table;
        } else {
            $this->alias = $alias;
        }
        $this->aliasToTable[$this->alias] = $this->table;
    }

    public function getDb() {
        return $this->db;
    }

    /**
     * @param string|array $fields
     * @param string|null $tableAlias
     * @return DbQuery
     * @throws DbQueryException
     */
    public function fields($fields, $tableAlias = null) {
        // find table and model by $tableAlias
        if (!empty($tableAlias)) {
            if (!isset($this->aliasToTable[$tableAlias])) {
                throw new DbQueryException($this, "DbQuery->fields(): Unknown table alias: [$tableAlias]");
            }
            $table = $this->aliasToTable[$tableAlias];
        } else {
            $table = $this->table;
            $tableAlias = $this->alias;
        }
        if (empty($this->models[$table])) {
            throw new DbQueryException($this, "DbQuery->fields(): DbModel for table [$table] not provided");
        }
        $model = $this->models[$table];
        // process columns
        if (empty($fields)) {
            $fields = array();
        } else if (!is_array($fields) && !is_string($fields)) {
            throw new DbQueryException($this, 'DbQuery->fields(): Something wrong passed as $fields arg - ' . print_r($fields, true));
        } else if (is_string($fields)) {
            if ($fields == '*') {
                $fields = array();
                // get only non-virtual fields
                foreach ($model->fields as $fieldName => $settings) {
                    if (empty($settings['virtual'])) {
                        $fields[] = $fieldName;
                    }
                }
            } else if (is_object($fields) && $fields instanceof DbExpr) {
                $fields = array($fields);
            } else if (!preg_match('%^[a-zA-Z][a-zA-Z0-9_,]+$%i', $fields)) {
                throw new DbQueryException($this, "DbQuery->fields(): Invalid field name [$fields]");
            } else {
                $fields = preg_split('%\s*,\s*%is', $fields);
            }
        }
        // store columns
        $this->fields[$tableAlias] = array();
        foreach ($fields as $fieldAlias => $fieldName) {
            if (empty($fieldName)) {
                throw new DbQueryException($this, "DbQuery->fields(): Empty field name detected");
            }
            if (is_object($fieldName) && $fieldName instanceof DbExpr) {
                // check if alias is in expression
                if (preg_match('%^(.+?)\s+as\s+`?(.+?)`?$%is', $fieldName->get(), $matches)) {
                    $fieldName = DbExpr::create($matches[1]);
                    if (empty($fieldAlias) || is_numeric($fieldAlias)) {
                        $fieldAlias = $matches[2];
                    }
                } else if (empty($fieldAlias) || is_numeric($fieldAlias)) {
                    $fieldName = $fieldName->get();
                    throw new DbQueryException($this, "DbQuery->fields(): expression [$fieldName] has no alias");
                }
                $fieldAlias = $this->buildFieldAlias($tableAlias, $fieldName, $fieldAlias);
                $this->fields[$tableAlias][$fieldAlias] = $fieldName;
            } else {
                $fieldInfo = $this->disassembleField($fieldName, $tableAlias);
                $fieldAlias = $this->buildFieldAlias($fieldInfo['tableAlias'], $fieldInfo['colName'], $fieldAlias);
                $this->fields[$tableAlias][$fieldAlias] = $fieldInfo['colName'];
            }
        }
        return $this;
    }

    /**
     * Add primary key field to $this->fields[$tableAlias] and return resulting array without modifying $this->fields[$tableAlias]
     * @param array $fields
     * @param string $tableAlias
     * @param string|null $fieldAlias
     * @return array
     * @throws DbQueryException
     */
    protected function addPkField($fields, $tableAlias, $fieldAlias = null) {
        if (empty($this->aliasToTable[$tableAlias])) {
            throw new DbQueryException($this, "DbQuery->addPkField(): unknown table alias [$tableAlias]");
        }
        $table = $this->aliasToTable[$tableAlias];
        if (
            !in_array($this->models[$table]->primaryKey, $fields)
            && !in_array($tableAlias . '.' . $this->models[$table]->primaryKey, $fields)
        ) {
            $fieldAlias = $this->buildFieldAlias($tableAlias, $this->models[$table]->primaryKey, $fieldAlias);
            $fields[$fieldAlias] = $this->models[$table]->primaryKey;
        }
        return $fields;
    }

    /**
     * Check if query fields have aggregate functions (min, max, count, avg, ...)
     * @return bool
     */
    protected function hasAggregatesInFields() {
        $concat = '';
        foreach ($this->fields as $fields) {
            $concat .= implode(' / ', $fields);
        }
        return !!preg_match('%(MIN|MAX|SUM|AVG|array_agg|bit_and|bit_or|bool_and|bool_or|count|every|string_agg|xmlagg)\(%is', $concat);
    }

    /**
     * Add single join
     * Note: $knownTableAlias must be known alias. It can be $this->alias or other already joined table
     * @param DbModel $relatedModel - model to join with
     * @param string|null $relatedAlias - join table alias. null = $relatedModel->alias (must not be $this->alias or any alias in $this->aliasToTable)
     * @param string $relatedColumn - column of 1st table
     * @param string|null $knownTableAlias - alias of known table. null = $this->alias. (it must be null, $this->alias or any alias in $this->aliasToTable)
     * @param string $knownTableColumn - column of 2nd table
     * @param array|string $fields - columns to fetch from $table1
     * @param string|null $type - type of join: 'inner', 'left', 'right', 'full'. default: 'inner'
     * @param bool|null|array $conditions - additional join conditions
     * @return DbQuery
     * @throws DbQueryException
     */
    public function join(DbModel $relatedModel, $relatedAlias, $relatedColumn, $knownTableAlias = null, $knownTableColumn = null,
    $fields = '*', $type = 'inner', $conditions = false) {
        if (empty($relatedColumn)) {
            throw new DbQueryException($this, 'DbQuery->join(): $relatedColumn is empty');
        }
        if (empty($relatedAlias)) {
            $relatedAlias = $relatedModel->alias;
        }
        if (isset($this->aliasToTable[$relatedAlias])) {
            throw new DbQueryException($this, "DbQuery->join(): table alias [$relatedAlias] already used");
        } else if (isset($this->joins[$relatedAlias])) {
            throw new DbQueryException($this, "DbQuery->join(): table with alias [$relatedAlias] already joined");
        }
        if (empty($knownTableAlias)) {
            $knownTableAlias = $this->alias;
            if (empty($knownTableColumn)) {
                $knownTableColumn = $this->models[$this->table]->primaryKey;
            }
        } else if (empty($this->aliasToTable[$knownTableAlias])) {
            throw new DbQueryException($this, "DbQuery->join(): table with alias [$knownTableAlias] is not known");
        }
        if (empty($knownTableColumn)) {
            $knownTableColumn = $this->models[$this->aliasToTable[$knownTableAlias]]->primaryKey;
        }
        $this->models[$relatedModel->table] = $relatedModel;
        $this->aliasToTable[$relatedAlias] = $relatedModel->table;
        $col1Info = $this->disassembleField($relatedColumn, $relatedAlias);
        $col2Info = $this->disassembleField($knownTableColumn, $knownTableAlias);
        $conditions = !empty($conditions) && is_array($conditions) ? $conditions : array();
        $conditions[] = $col1Info['assembled'] . '=' . $col2Info['assembled'];
        $this->joins[$relatedAlias] = array(
            'type' => in_array(strtolower(trim($type)), array('inner', 'left', 'right', 'full')) ? strtolower(trim($type)) : 'inner',
            'table' => $this->quoteName($relatedModel->schema . '.' . $relatedModel->table) . ' AS ' . $this->quoteName($relatedAlias),
            'on' => '(' . $this->assembleConditions($conditions) . ')',
        );
        $this->fields($fields, $relatedAlias);
        return $this;
    }

    // todo: add joinWithCustomConditions(DbModel  $relatedModel, $relatedAlias, $conditions, $fields = '*', $type = 'inner') when will be needed

    /**
     * Add single left join
     * Note: $knownTableAlias must be known alias. It can be $this->alias or other already joined table
     * @param DbModel $relatedModel - model to join with
     * @param string|null $relatedAlias - join table alias. null = $relatedModel->alias (must not be $this->alias or any alias in $this->aliasToTable)
     * @param string $relatedColumn - column of 1st table
     * @param string|null $knownTableAlias - alias of known table. null = $this->alias. (it must be null, $this->alias or any alias in $this->aliasToTable)
     * @param string $knownTableColumn - column of 2nd table
     * @param array|string $fields - columns to fetch from $table1
     * @return DbQuery
     * @throws DbQueryException
     */
    public function leftJoin(DbModel  $relatedModel, $relatedAlias, $relatedColumn, $knownTableAlias = null, $knownTableColumn = null, $fields = '*') {
        return $this->join($relatedModel, $relatedAlias, $relatedColumn, $knownTableAlias, $knownTableColumn, $fields, 'left');
    }

    /**
     * Add single right join
     * Note: $knownTableAlias must be known alias. It can be $this->alias or other already joined table
     * @param DbModel $relatedModel - model to join with
     * @param string|null $relatedAlias - join table alias. null = $relatedModel->alias (must not be $this->alias or any alias in $this->aliasToTable)
     * @param string $relatedColumn - column of 1st table
     * @param string|null $knownTableAlias - alias of known table. null = $this->alias. (it must be null, $this->alias or any alias in $this->aliasToTable)
     * @param string $knownTableColumn - column of 2nd table
     * @param array|string $fields - columns to fetch from $table1
     * @return DbQuery
     * @throws DbQueryException
     */
    public function rightJoin(DbModel  $relatedModel, $relatedAlias, $relatedColumn, $knownTableAlias = null, $knownTableColumn = null, $fields = '*') {
        return $this->join($relatedModel, $relatedAlias, $relatedColumn, $knownTableAlias, $knownTableColumn, $fields, 'right');
    }

    /**
     * Add single full join
     * Note: $knownTableAlias must be known alias. It can be $this->alias or other already joined table
     * @param DbModel $relatedModel - model to join with
     * @param string|null $relatedAlias - join table alias. null = $relatedModel->alias (must not be $this->alias or any alias in $this->aliasToTable)
     * @param string $relatedColumn - column of 1st table
     * @param string|null $knownTableAlias - alias of known table. null = $this->alias. (it must be null, $this->alias or any alias in $this->aliasToTable)
     * @param string $knownTableColumn - column of 2nd table
     * @param array|string $fields - columns to fetch from $table1
     * @return DbQuery
     * @throws DbQueryException
     */
    public function fullJoin(DbModel  $relatedModel, $relatedAlias, $relatedColumn, $knownTableAlias = null, $knownTableColumn = null, $fields = '*') {
        return $this->join($relatedModel, $relatedAlias, $relatedColumn, $knownTableAlias, $knownTableColumn, $fields, 'full');
    }

    /**
     * Remove join by alias or all joins if $alias == 'all'
     * @param string $alias - alias of join | 'all'
     * @return DbQuery
     */
    public function removeJoin($alias) {
        if (!empty($alias)) {
            if (strtolower($alias) == 'all') {
                foreach($this->joins as $alias => $info) {
                    unset($this->aliasToTable[$alias], $this->fields[$alias]);
                }
                $this->joins = array();
            } else if (isset($this->joins[$alias])) {
                unset($this->joins[$alias], $this->aliasToTable[$alias], $this->fields[$alias]);
            }
        }
        return $this;
    }

    /**
     * Set distinct flag to query (SELECT DISTINCT fields ...)
     * @param bool $value
     * @return DbQuery
     */
    public function distinct($value = true) {
        $this->distinct = !!$value;
        return $this;
    }

    /**
     * Set Conditions
     * @param null|array|DbExpr|string $conditions
     * @return DbQuery
     */
    public function where($conditions = null) {
        $this->where = $conditions;
        return $this;
    }

    /**
     * Add ORDER BY
     * @param string|array|DbExpr $orderBy =
     *  1. 'field1 ASC' or 'ModelAlias.field1 desc'
     *  2. 'field1 asc, ModelAlias.field2 desc'
     *  3. array('field1 asc', 'ModelAlias.field2 desc')
     *  When ModelAlias omitted - $this->alias is used
     * @param bool $append - true: add $orderBy to existing sorting | false: replace existsing sorting
     * @return DbQuery
     * @throws DbQueryException
     */
    public function orderBy($orderBy, $append = true) {
        if (!empty($orderBy) && !is_string($orderBy) && !is_array($orderBy) && (is_object($orderBy) && !($orderBy instanceof DbExpr))) {
            throw new DbQueryException($this, 'DbQuery->order(): Something wrong passed as $orderBy arg - ' . print_r($orderBy, true));
        }
        if (!$append) {
            $this->orderBy = array();
        }
        if (!empty($orderBy)) {
            if (is_string($orderBy)) {
                $orderBy = preg_split('%\s*,\s*%is', $orderBy);
            } else if (is_object($orderBy)) {
                $orderBy = array($orderBy);
            }
            foreach ($orderBy as $orderField) {
                $direction = 'ASC';
                $field = $orderField;
                if (is_object($orderField)) {
                    /** @var DbExpr $orderField */
                    $orderField = $this->replaceQuotes($orderField->get());
                }
                if (preg_match('%^(.*?)\s+(ASC|DESC)%is', $orderField, $parts)) {
                    $direction = strtoupper(trim($parts[2]));
                    $field = $parts[1];
                }
                $colInfo = $this->disassembleField($field);
                if (!is_object($colInfo['assembled'])) {
                    $this->orderBy[$colInfo['assembled']] = $direction;
                } else {
                    $this->orderBy[$this->replaceQuotes($colInfo['assembled']->get())] = $direction;
                }
            }
        }
        return $this;
    }

    /**
     * Add GROUP BY
     * @param array $columns - can contain 'col1' and 'ModelAlias.col1'
     *      When ModelAlias omitted - $this->alias is used
     * @param bool $append - true: add $orderBy to existing grouping | false: replace existsing grouping
     * @return DbQuery
     * @throws DbQueryException
     */
    public function groupBy($columns, $append = true) {
        if (!empty($columns) && !is_string($columns) && !is_array($columns)) {
            throw new DbQueryException($this, 'DbQuery->group(): Something wrong passed as $columns arg - ' . print_r($columns, true));
        }
        if (!$append) {
            $this->groupBy = array();
        }
        if (!empty($columns)) {
            if (!is_array($columns)) {
                $columns = array($columns);
            }
            foreach ($columns as $colName) {
                $colInfo = $this->disassembleField($colName);
                $this->groupBy[] = $colInfo['assembled'];
            }
        }
        return $this;
    }

    /**
     * Set LIMIT
     * @param int $limit
     * @return DbQuery
     */
    public function limit($limit) {
        if ((is_numeric($limit) || is_int($limit) || is_float($limit)) && floor(floatval($limit)) > 0) {
            $this->limit = floor(floatval($limit));
        } else {
            unset($this->limit);
        }
        return $this;
    }

    /**
     * Remove LIMIT
     * @return DbQuery
     */
    public function noLimit() {
        unset($this->limit);
        return $this;
    }

    /**
     * Set/Remove OFFSET
     * @param int|null $offset - null or 0: remove offset
     * @return DbQuery
     */
    public function offset($offset) {
        if ((is_numeric($offset) || is_int($offset) || is_float($offset)) && floor(floatval($offset)) > 0) {
            $this->offset = floor(floatval($offset));
        } if (empty($offset)) {
            unset($this->offset);
        }
        return $this;
    }

    /**
     * Set LIMIT and OFFSET at once
     * @param int $limit
     * @param int $offset
     * @return DbQuery
     */
    public function page($limit, $offset = 0) {
        return $this->limit($limit)->offset($offset);
    }

    /**
     * Process $options and extract query settings
     * Supported keys: 'FIELDS', 'CONDITIONS', 'ORDER', 'GROUP', 'OFFSET', 'LIMIT', 'JOIN', 'HAVING'
     * Other data treated as conditions
     * Note: if 'CONDITIONS' key not empty - it's value will be used instead of any other conditions placed outside
     * @param array $options
     * @return DbQuery
     * @throws DbQueryException
     */
    public function fromOptions($options) {
        if (!empty($options)) {
            if (is_string($options)) {
                $this->where($options);
            } else if (is_array($options)) {
                $optionsKeys = array('FIELDS', 'CONDITIONS', 'ORDER', 'GROUP', 'OFFSET', 'LIMIT', 'JOIN', 'HAVING', 'DISTINCT');
                // conditions
                if (!empty($options['CONDITIONS'])) {
                    $this->where($options['CONDITIONS']);
                } else {
                    $this->where(array_diff_key($options, array_flip($optionsKeys)));
                }
                if (!empty($options['FIELDS'])) {
                    $this->fields($options['FIELDS']);
                }
                if (array_key_exists('DISTINCT', $options)) {
                    $this->distinct(!!$options['DISTINCT']);
                }
                if (!empty($options['JOIN'])) {
                    foreach ($options['JOIN'] as $relatedAlias => $join) {
                        if (empty($join['table1_model']) || empty($join['table1_field']) || empty($join['table2_field'])){
                            throw new DbQueryException($this, 'DbQuery->fromOptions(): invalid join settings - ' . print_r($join, true));
                        }
                        if (!empty($join['table1_alias'])) {
                            $relatedAlias = $join['table1_alias'];
                        } else if (empty($relatedAlias)) {
                            $relatedAlias = null;
                        }
                        $this->join(
                            $join['table1_model'],
                            $relatedAlias,
                            $join['table1_field'],
                            $join['table2_alias'],
                            $join['table2_field'],
                            array_key_exists('fields', $join) ? $join['fields'] : '*',
                            !empty($join['type']) ? $join['type'] : 'inner',
                            !empty($join['conditions']) ? $join['conditions'] : false
                        );
                    }
                }
                if (!empty($options['ORDER'])) {
                    $this->orderBy($options['ORDER']);
                }
                if (!empty($options['GROUP'])) {
                    $this->groupBy($options['GROUP']);
                }
                if (array_key_exists('LIMIT', $options)) {
                    $this->limit($options['LIMIT']);
                }
                if (array_key_exists('OFFSET', $options)) {
                    $this->offset($options['OFFSET']);
                }
                if (!empty($options['HAVING'])) {
                    // for future
                }
            } else {
                throw new DbQueryException($this, 'DbQuery->fromOptions(): something wrong passed as $options - ' . print_r($options, true));
            }
        }
        return $this;
    }

    /**
     * Build and execute query, process and return records
     * @param string $type - one of 'all', 'first'.
     * @param bool $withRootTableAlias -
     *      true: result will look like array(0 => array('RootAlias' => array('column1' => 'value1', ...), 1 => array(...), ...)
     *      false: result will look like array(0 => array('column1' => 'value1', ...), 1 => array(...), ...)
     * @return array of records
     */
    public function find($type = 'all', $withRootTableAlias = true) {
        $query = $this->buildQuery($type);
        if (!$this->db->inTransaction()) {
            $this->db->begin(true, Db::PGSQL_TRANSACTION_TYPE_REPEATABLE_READ);
            $localTransaction = true;
        }
        $statement = $this->db->query($query);
        if (!empty($localTransaction)) {
            $this->db->commit();
        }
        return $this->processRecords($statement, $type, $withRootTableAlias);
    }

    /**
     * Calculate result of an expression that returns single value
     * @param string $expression - some expression to fetch. For example: 'COUNT(*)' or '1'
     * @param array $conditionsAndOptions - query conditions and options
     * @return string|int|float|bool
     */
    public function expression($expression, $conditionsAndOptions = array()) {
        $expression = $this->replaceQuotes($expression);
        return $this->fromOptions($conditionsAndOptions)->find($expression, false);
    }

    /**
     * Get 1 record
     * Shortcut for $this->find('first')
     * @param bool $withRootTableAlias -
     *      true: result will look like array(0 => array('RootAlias' => array('column1' => 'value1', ...), 1 => array(...), ...)
     *      false: result will look like array(0 => array('column1' => 'value1', ...), 1 => array(...), ...)
     * @return array
     */
    public function findOne($withRootTableAlias = false) {
        return $this->find('first', $withRootTableAlias);
    }

    /**
     * if db engine has 'RETURNING' statement - build it, otherwise - build select query
     * @param null|bool|string|array $returning
     *      string: something compatible with RETURNING for postgresql query ('*' = all fields)
     *      array: list of fields to return
     *      null: return pk value
     *      true: return all fields ('*')
     *      false: return nothing
     * @return string
     */
    protected function buildReturning($returning) {
        if ($returning === false) {
            return '';
        }
        $model = $this->models[$this->table];
        if ($returning === null) {
            $fields = $model->primaryKey;
        } else if ($returning === true) {
            $fields = '*';
        } else if (is_string($returning) || is_array($returning)) {
            $fields = $returning;
        } else {
            return '';
        }
        $this->fields($fields);
        if ($this->db->hasReturning) {
            return ' RETURNING ' . $this->buildFieldsList(false, true);
        } else {
            return $this;
        }
    }

    /**
     * Run DELETE query
     * @param bool|string $returning - something compatible with RETURNING for postgresql query
     *      http://www.postgresql.org/docs/9.2/static/sql-delete.html
     * @return int|array
     *      - int: affected rown when $returning === false or 'RETURNING' statement not supported
     *      - array: according to $returning
     */
    public function delete($returning = false) {
        $this->query = 'DELETE FROM ' . $this->quoteName($this->table);
        $where = '';
        if (!empty($this->where)) {
            $conditions = trim($this->assembleConditions($this->where));
            if (!empty($conditions)) {
                $where .= ' WHERE ' . $conditions . ' ';
            }
        }
        if (!empty($this->orderBy) || !empty($this->limit)) {
            $model = $this->models[$this->table];
            $subquery = 'SELECT ' . $this->quoteName($model->primaryKey)
                . ' FROM ' . $this->quoteName($this->table)
                . ' AS ' . $this->quoteName($this->alias)
                 . $where;
            if (!empty($this->orderBy)) {
                $sorting = array();
                foreach ($this->orderBy as $field => $direction) {
                    $sorting[] = " $field $direction ";
                }
                $subquery .= ' ORDER BY ' . implode(', ', $sorting) . ' ';
            }
            // limit and offset
            if (!empty($this->limit)) {
                $subquery .= " LIMIT {$this->limit} ";
            }
            $this->query .= ' WHERE ' . $this->quoteName($model->primaryKey) . ' IN (' . $subquery . ')';
        } else {
            $this->query .= ' AS ' . $this->quoteName($this->alias) . $where;
        }
        $returning = $this->buildReturning($returning);
        if ($this->db->hasReturning && !empty($returning)) {
            $this->query .= $returning;
            //$this->quoteQueryExpressions();
            $statement = $this->db->query($this->query);
            $result = $this->processRecords($statement, 'all');
            return $result;
        } else {
            //$this->quoteQueryExpressions();
            return $this->db->exec($this->query);
        }
    }

    /**
     * insert single records to db
     * @param array $data
     * @param null|bool|string|array $returning
     *      string: something compatible with RETURNING for postgresql query ('*' = all fields)
     *      array: list of fields to return
     *      null: return pk value
     *      true: return all fields ('*')
     *      false: return nothing
     * @return bool|int|string|array
     *  - false: failed to insert record
     *  - null: inserted, but no result returned by $returning
     *  - string or int: primary key value of just inserted value
     */
    public function insert($data, $returning = null) {
        $this->query = 'INSERT INTO ' . $this->quoteName($this->table) . ' ';
        $values = array();
        $model = $this->models[$this->table];
        foreach ($data as $key => $value) {
            if (isset($model->fields[$key]) && empty($model->fields[$key]['virtual'])) {
                $values[$this->quoteName($key)] = $this->quoteValue($value);
            }
            // todo: maybe throw exception when unknown field detected?
        }
        $this->query .= ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', $values) . ')';
        if (empty($returning)) {
            $returning = null; //< force primary key return
        }
        // process query and required return
        $returning = $this->buildReturning($returning);
        if (is_string($returning)) {
            $this->query .= $returning;
        }
        //$this->quoteQueryExpressions();
        $statement = $this->db->query($this->query);
        if (!$statement || !$statement->rowCount()) {
            return false;
        }
        if ($this->db->hasReturning) {
            $result = $this->processRecords($statement, 'first');
        } else {
            $pkValue = $this->db->pdo->lastInsertId();
            if (empty($returning)) {
                return $pkValue;
            } else {
                $this->where(array($model->primaryKey => $pkValue));
                $result = $this->findOne(false);
            }
        }
        if (empty($result)) {
            return null;
        } else if (count($result) == 1) {
            // 1 field
            return array_shift($result);
        } else {
            return $result;
        }
    }

    /**
     * Insert many records at once
     * @param array $fieldNames - field names use
     * @param array[] $rows - arrays of values for $fieldNames
     * @param null|bool|string|array $returning
     *      string: something compatible with RETURNING for postgresql query ('*' = all fields)
     *      array: list of fields to return
     *      null: return pk value
     *      true: return all fields ('*')
     *      false: return nothing
     *   !!! Warning: not possible for db engines that do not support 'RETURNING' statement
     * @return int|array - int: amount of rows affected | array: list of data provided by RETURNING
     * @throws DbQueryException
     */
    public function insertMany($fieldNames, $rows, $returning = false) {
        if (empty($rows)) {
            return 0;
        } else if (empty($fieldNames)) {
            throw new DbQueryException($this, 'DbQuery->insertMany() - set of field names to save cannot be empty');
        }
        $this->query = 'INSERT INTO ' . $this->quoteName($this->table) . ' (' . implode(', ', $this->quoteName($fieldNames)) . ') VALUES ';
        $queryValues = array();
        foreach ($rows as $data) {
            $values = array();
            foreach ($fieldNames as $fieldName) {
                if (!array_key_exists($fieldName, $data)) {
                    $values[] = $this->quoteValue('');
                } else {
                    $values[] = $this->quoteValue($data[$fieldName]);
                }
            }
            $queryValues[] = ' (' . implode(', ', $values) . ') ';
        }
        $this->query .= implode(', ', $queryValues);
        // add returnng query
        $returning = $this->buildReturning($returning);
        if (empty($returning) || !$this->db->hasReturning) {
//            $this->quoteQueryExpressions();
            return $this->db->exec($this->query);
        } else {
            $this->query .= $returning;
//            $this->quoteQueryExpressions();
            $statement = $this->db->query($this->query);
            if (!$statement || !$statement->rowCount()) {
                return 0;
            }
            $result = $this->processRecords($statement, 'all', false);
            return empty($result) ? $statement->rowCount() : $result;
        }
    }

    /**
     * @param array $data
     *  special keys:
     *      ("field +=" => value) == (field += value)
     *      ("field -=" => value) == (field -= value)
     * @param null|bool|string|array $returning
     *      string: something compatible with RETURNING for postgresql query ('*' = all fields)
     *      array: list of fields to return
     *      null: return pk value
     *      true: return all fields ('*')
     *      false: return nothing
     *   !!! Warning: not possible for db engines that do not support 'RETURNING' statement
     * @return int|array - int: amount of records updated | array: data provided by RETURNING
     * @throws DbQueryException
     */
    public function update($data, $returning = false) {
        $this->query = 'UPDATE ' . $this->quoteName($this->table) . ' AS ' . $this->quoteName($this->alias) . ' SET ';
        $fields = array();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $fields[] = $this->quoteName($key) . ' = ' . $this->quoteValue($value);
            } else if (is_object($value) && $value instanceof DbExpr) {
                $fields[] = $this->quoteName($key) . ' = ' . $this->replaceQuotes($value->get());
            } else if (preg_match('/([\w]+)\s*(\+|\-)=\s*$/i', $key, $match)) {
                if (is_numeric($value)) {
                    $field = $this->quoteName($match[1]);
                    $fields[] = $field . ' = ' . $field . ' ' . $match[2] . ' ' . $this->quoteValue($value);
                }
            } else {
                $fields[] = $this->quoteName($key) . ' = ' . $this->quoteValue($value);
            }
        }
        $this->query .= implode(',', $fields);
        if (empty($this->where)) {
            throw new DbQueryException($this, 'DbQuery->update() - update without conditions');
        } else {
            $conditions = trim($this->assembleConditions($this->where));
            if (!empty($conditions)) {
                $this->query .= ' WHERE ' . $conditions . ' ';
            }
        }
        $returning = $this->buildReturning($returning);
        if (empty($returning) || !$this->db->hasReturning) {
//            $this->quoteQueryExpressions();
            return $this->db->exec($this->query);
        } else {
            $this->query .= $returning;
//            $this->quoteQueryExpressions();
            $statement = $this->db->query($this->query);
            $result = $this->processRecords($statement, 'all');
            if (empty($result)) {
                return $statement->rowCount();
            } else {
                return $result;
            }
        }
    }

    /**
     * Clean query (leave only model, table name and table alias)
     */
    public function reset() {
        $this->fields = array();
        $this->where = array();
        $this->joins = array();
        $this->orderBy = array();
        $this->groupBy = array();
        unset($this->limit);
        $this->offset = 0;
        $this->having = '';
        $this->query = '';
        $this->models = array($this->table => $this->models[$this->table]);
        $this->aliasToTable = array($this->alias => $this->table);
    }

    /**
     * Build DB query from parts
     * @param string $type - type of query: 'all', 'first', expression, integer
     *      'all' and 'first': same query with different limits
     *      'expression' and integer: only 1 field and 1 record fetched
     * @return string
     */
    public function buildQuery($type = 'all') {
        $type = strtolower($type);
        $autoAddPkFieldAndOrder = empty($this->groupBy) && !$this->hasAggregatesInFields();
        $this->query = 'SELECT ';
        if ($this->distinct) {
            $this->query .= 'DISTINCT ';
        }
        // fields
        if (!in_array($type, array('all', 'first'))) {
            $this->query .= ' ' . $type . ' ';
            $autoAddPkFieldAndOrder = false;
        } else {
            if (empty($this->fields)) {
                $this->fields('*');
                if (!empty($this->joins)) {
                    foreach ($this->joins as $joinAlias => $joinInfo) {
                        $this->fields('*', $joinAlias);
                    }
                }
            }
            $this->query .= $this->buildFieldsList($autoAddPkFieldAndOrder);
        }
        // table
        $this->query .= ' FROM ' . $this->quoteName($this->table) . ' AS ' . $this->quoteName($this->alias) . ' ';
        // joins
        if (!empty($this->joins)) {
            foreach ($this->joins as $joinInfo) {
                $this->query .= ' ' . strtoupper($joinInfo['type']) . ' JOIN ' . $joinInfo['table'] . ' ON ' . $joinInfo['on'] . ' ';
            }
        }
        // where
        if (!empty($this->where)) {
            $conditions = trim($this->assembleConditions($this->where));
            if (!empty($conditions)) {
                $this->query .= ' WHERE ' . $conditions . ' ';
            }
        }
        // group by
        if (!empty($this->groupBy)) {
            $this->query .= ' GROUP BY ' . implode(', ', $this->groupBy) . ' ';
        }
        // order by
        if (empty($this->orderBy) && $autoAddPkFieldAndOrder) {
            $this->orderBy($this->models[$this->table]->primaryKey . ' asc');
        }
        if (!empty($this->orderBy)) {
            $sorting = array();
            foreach ($this->orderBy as $field => $direction) {
                $sorting[] = " $field $direction ";
            }
            $this->query .= ' ORDER BY ' . implode(', ', $sorting) . ' ';
        }
        // limit and offset
        if (!empty($this->limit)) {
            $this->query .= " LIMIT {$this->limit} ";
        }
        if (!empty($this->offset)) {
            $this->query .= " OFFSET {$this->offset} ";
        }
        // quote expressions
        //$this->quoteQueryExpressions();
        return $this->query;
    }

    /**
     * Assemble fields list for query
     * @param bool $autoAddPkField - true: add pk field if it is absent
     * @param bool $doNotAddAnyAliases - true: will not add table alias and column alias to fields, leave only plain field_name
     * @return string
     */
    protected function buildFieldsList($autoAddPkField, $doNotAddAnyAliases = false) {
        $allColumns = array();
        foreach ($this->fields as $tableAlias => $fields) {
            if ($autoAddPkField) {
                $fields = $this->addPkField($fields, $tableAlias);
            }
            foreach ($fields as $colAlias => $fieldName) {
                if ((is_object($fieldName) && $fieldName instanceof DbExpr) || $doNotAddAnyAliases) {
                    $fieldName = $this->quoteName($fieldName);
                } else {
                    $fieldName = $this->quoteName($tableAlias . '.' . $fieldName);
                }
                if ($doNotAddAnyAliases) {
                    $allColumns[] = $fieldName;
                } else {
                    $allColumns[] = $fieldName . ' AS ' . $this->quoteName($colAlias);
                }
            }
        }
        return implode(', ', $allColumns);
    }

    public function __toString() {
        return $this->buildQuery();
    }

    /**
     * Make column alias using passed data
     * @param string $tableAlias
     * @param string $colName
     * @param string|int|null $colAlias
     *  - int or empty: column alias will be generated from $tableAlias and $colName
     *  - not empty: column alias will be generated from $tableAlias and $colAlias
     * @return string
     */
    protected function buildFieldAlias($tableAlias, $colName, $colAlias = null) {
        return '__' . $tableAlias . '__' . (empty($colAlias) || is_numeric($colAlias) ? $colName : $colAlias);
    }

    /**
     * Disassemble $colName and return information about column and table it belongs to
     * Note:
     *  1. If $tableAlias is unknown - throws exception
     *  2. If $model is unknown - throws exception
     *  3. If $colName is unknown - throws exception
     * @param string $colName:
     *  1. 'column1' => array(
     *    'tableAlias' => $this->alias,
     *    'colName' => 'column1',
     *    'table' => $this->table,
     *    'model' => $this->models[$this->table],
     *    'data_type' => '',
     *    'assembled' => "$this->alias"."column1"
     *  )
     *  2. 'TableAlias.column1' => array(
     *    'tableAlias' => 'TableAlias',
     *    'colName' => 'column1',
     *    'table' => 'table',
     *    'model' => $this->models['table'],
     *    'data_type' => '',
     *    'assembled' => "TableAlias"."column1"
     *  )
     *  3. 'TableAlias.column1::varchar' => array(
     *    'tableAlias' => 'TableAlias',
     *    'colName' => 'column1',
     *    'table' => 'table',
     *    'model' => $this->models['table'],
     *    'data_type' => '::varchar',
     *    'assembled' => '"TableAlias"."column1"::varchar'
     *  )
     * @param string|null $tableAlias - used if column contains no table alias and does not belong to $this->table
     * @return array = array(
     *    'tableAlias' => $tableAlias,
     *    'colName' => $colName,
     *    'table' => $table,
     *    'model' => $model,
     *    'data_type' => $alterDataType,
     *    'assembled' => $this->quoteName($tableAlias . '.' . $colName)
     * )
     * @throws DbQueryException
     */
    public function disassembleField($colName, $tableAlias = null) {
        $alterDataType = '';
        if (is_object($colName) && $colName instanceof DbExpr) {
            //$colName = $colName->get();
            return array(
                'tableAlias' => '',
                'colName' => $colName,
                'table' => '',
                'model' => '',
                'data_type' => '',
                'assembled' => $colName
            );
        } if (preg_match('%^\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)(::[a-zA-Z0-9 _]+)?\s*$%is', $colName, $fieldParts)) {
            $colName = $fieldParts[2];
            $tableAlias = $fieldParts[1];
            $alterDataType = !empty($fieldParts[3]) ? $fieldParts[3] : '';
            if (!isset($this->aliasToTable[$tableAlias])) {
                throw new DbQueryException($this, "DbQuery->disassembleField(): unknown table alias [$tableAlias]");
            }
            $table = $this->aliasToTable[$tableAlias];
        } else if (!empty($tableAlias)) {
            if (!isset($this->aliasToTable[$tableAlias])) {
                throw new DbQueryException($this, "DbQuery->disassembleField(): unknown table alias [$tableAlias]");
            }
            $table = $this->aliasToTable[$tableAlias];
        } else {
            $tableAlias = $this->alias;
            $table = $this->table;
        }
        if (empty($this->models[$table])) {
            throw new DbQueryException($this, "DbQuery->disassembleField(): DbModel for table [$table] not provided");
        }
        $model = $this->models[$table];
        // check if column name is like 'name::type' and split it
        if (preg_match('%^([a-zA-Z0-9_]+)(\:\:[a-zA-Z0-9 _]+)$%is', $colName, $matches)) {
            $colName = $matches[1];
            $alterDataType = $matches[2];
        }
        if (!isset($model->fields[$colName]) && empty($model->fields[$colName]['virtual'])) {
            throw new DbQueryException($this, "DbQuery->disassembleField(): unknown column [$colName] in table [$table]");
        }
        return array(
            'tableAlias' => $tableAlias,
            'colName' => $colName,
            'table' => $table,
            'model' => $model,
            'data_type' => $alterDataType,
            'assembled' => $this->quoteName($tableAlias . '.' . $colName) . $alterDataType
        );
    }

    /**
     * Quote DB name (column, table, alias, schema)
     * @param string|array $name - array: list of names to quote.
     * Names format:
     *  1. 'table', 'column', 'TableAlias'
     *  2. 'TableAlias.column' - quoted like '`TableAlias`.`column`'
     *  3. DbExpr object - converted to string
     * @param bool $recursion - true: means that this method called itself. This will restrict 3rd level recursion
     * @return string
     * @throws DbQueryException
     */
    public function quoteName($name, $recursion = false) {
        if (!$recursion && is_array($name)) {
            $ret = array();
            foreach ($name as $subName) {
                $ret[] = $this->quoteName($subName, true);
            }
            return $ret;
        } else if (is_object($name) && $name instanceof DbExpr) {
            return $this->replaceQuotes($name->get());
        } else if (!is_string($name) || empty($name)) {
            throw new DbQueryException($this, "DbQuery->quoteName(): invalid column name [$name]");
        } else if (!preg_match('%^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)?$%i', $name)) {
            throw new DbQueryException($this, "DbQuery->quoteName(): invalid column name [$name]");
        } else {
            return $this->db->quoteName($name);
        }
    }

    /**
     * Quote passed value
     * @param mixed $value
     * @param int|array $fieldInfoOrType - one of \PDO::PARAM_* or Model->field[$col]
     * @return string
     */
    public function quoteValue($value, $fieldInfoOrType = \PDO::PARAM_STR) {
        return $this->db->quoteValue($value, $fieldInfoOrType);
    }

    /**
     * Quote set of values
     * @param array $values
     * @return array
     */
    public function quoteValues($values) {
        $ret = array();
        foreach ($values as $value) {
            $ret[] = $this->quoteValue($value);
        }
        return $ret;
    }

    /**
     * Quote expressions in $this->query
     */
    protected function quoteQueryExpressions() {
        $this->query = $this->replaceQuotes($this->query);
    }

    public function replaceQuotes($expression) {
        $expression = preg_replace_callback('%``(.*?)``%is', array($this, 'regexpQuoteValue'), $expression);
        $expression = preg_replace_callback('%`(.*?)`%is', array($this, 'regexpQuoteName'), $expression);
        return $expression;
    }

    /**
     * Quote value extracted by regexp
     * @param array $matches - regexp matches
     * @return string
     */
    public function regexpQuoteValue($matches) {
        return $this->quoteValue($matches[1]);
    }

    /**
     * Quote name extracted by regexp
     * @param array $matches - regexp matches
     * @return string
     */
    public function regexpQuoteName($matches) {
        return $this->quoteName($matches[1]);
    }

    /**
     * Assemble conditions
     * Resursive
     * @param null|string|array $conditions
     * @param null|string|array $glue - 'AND' | 'OR'
     * @return string
     * @throws DbQueryException
     */
    public function assembleConditions($conditions = null, $glue = 'AND') {
        if (empty($conditions) || $conditions === true || (is_numeric($conditions) && $conditions == 1)) {
            return '';
        } else if (is_string($conditions) || (is_object($conditions) && $conditions instanceof DbExpr)) {
            if (is_object($conditions)) {
                $conditions = $this->replaceQuotes($conditions->get());
            }
            if (empty($conditions) || trim($conditions) == '') {
                return '';
            } else {
                return $conditions;
            }
        } else if (is_array($conditions)) {
            $assembled = array();
            foreach ($conditions as $column => $value) {
                // we have next cases:
                // 0-global. $value and/or $column is instance of DbExpr (treated as quoted value and converted to string)
                // 1. condition is $value, $column - just index (this is assembled condition - no processing needed)

                // 2. condition is ($column => $value) where $value is string (results in 'colname' = 'value')
                // 2.1 $column contains equation sign (for example 'colname >', results in 'colname' > 'value')
                //     Short list of equations: >, <, =, !=, >=, <=, LIKE, ~, ~*, !~, !~*, SIMILAR TO, IN; NOT + any
                // 2.2 $value === null, $column may contain 'NOT' (results in 'colname' IS NULL or 'colname' IS NOT NULL)
                // 2.3 $column contains 'BETWEEN' or 'NOT BETWEEN' and $value is array or string like 'val1 and val2' (results in 'colname' BETWEEN a and b)
                // 2.4 $column contains no equation sign or contains '!=' or 'NOT', $value is array (results in 'colname' IN (a,b,c))

                // 3. $column is any of 'AND', 'OR' or $value == array() (can be recursive)
                // 3.1. $column == 'AND' and $value == array() or $column != 'OR' and $value == array() - recursion: $this->assembleConditions($value)
                // 3.2. $column == 'OR' and $value == array() - recursion: $this->assembleConditions($value, 'OR')

                $valueQuoted  = false;
                $columnQuoted  = false;

                // 0-global - custom expressions
                if (is_object($value) && $value instanceof DbExpr) {
                    $valueQuoted = true;
                    $value = $this->replaceQuotes($value->get());
                }
                if (is_object($column) && $column instanceof DbExpr) {
                    $columnQuoted = true;
                    $column = $this->replaceQuotes($column->get());
                }

                $operator = '=';
                if (is_numeric($column) && is_string($value)) {
                    // 1
                    if (!empty($value) && trim($value) != '') {
                        $assembled[] = $value;
                    }
                } else if (is_string($column) && !in_array(strtolower(trim($column)), array('and', 'or'))) {
                    // 2.1
                    $customOperator = $columnQuoted ? '' : '\s+(.+?)\s*$|'; //< when column is expression ($columnQuoted = true) it is possible to fail custom operator search
                    if (preg_match('%^\s*(.*?)(?:' . $customOperator . '\s*(>|<|=|\!=|>=|<=|LIKE|NOT\s+LIKE|\~\*|\~|!\~\*|!\~|SIMILAR\s+TO|NOT\s+SIMILAR\s+TO|IN|NOT IN))\s*$%is', $column, $matches)) {
                        if (trim($matches[1]) == '') {
                            throw new DbQueryException($this, "DbQuery->assembleConditions(): empty column name in [$column]");
                        }
                        $column = trim($matches[1]);
                        $operator = strtoupper(trim(empty($matches[2]) ? $matches[3] : $matches[2]));
                    }
                    if (empty($operator)) {
                        // 2
                        $operator = '=';
                    }
                    if ($value === null || (is_string($value) && strtolower($value) === 'null')) {
                        // 2.2
                        if ($operator == '!' || $operator == '!=' || $operator == 'NOT' || $operator == 'IS NOT') {
                            $operator = 'IS NOT';
                        } else {
                            $operator = 'IS';
                        }
                    } else if ($operator == 'BETWEEN' || $operator == 'NOT BETWEEN') {
                        // 2.3
                        if (!empty($value) && is_string($value) && preg_match('%^\s*(.+?)\s+AND\s+(.*)\s*$%is', $value, $betweenMatches)) {
                            $value = array($betweenMatches[1], $betweenMatches[2]);
                        } else if (empty($value) || !is_array($value)) {
                            throw new DbQueryException($this, "DbQuery->assembleConditions(): empty value for '$column IN (...)' condition");
                        } else if (count($value) != 2){
                            throw new DbQueryException($this, 'DbQuery->assembleConditions(): incorrect amount of values provided for BETWEEN condition: ' . print_r($value, true));
                        }
                        $value = $this->quoteValue(array_shift($value)) . ' AND ' . $this->quoteValue(array_shift($value));
                        $valueQuoted = true;
                    } else if (is_array($value)) {
                        // 2.4
                        if (empty($value)) {
                            throw new DbQueryException($this, "DbQuery->assembleConditions(): empty value for '$column IN (...)' condition");
                        } else {
                            switch ($operator) {
                                case '=':
                                    $operator = 'IN';
                                    break;
                                case '!=':
                                    $operator = 'NOT IN';
                                    break;
                                case 'NOT':
                                    $operator = 'NOT IN';
                                    break;
                                case 'IN':
                                case 'NOT IN':
                                    break;
                                default:
                                    throw new DbQueryException($this, "DbQuery->assembleConditions(): cannot use operator [$operator] to compare with array of values");
                            }
                            $value = '(' . implode(',', $this->quoteValues($value)) . ')';
                            $valueQuoted = true;
                        }
                    } else if ($operator == 'NOT' || $operator == 'IS NOT') {
                        // 2.1 - when $value !== null and value not array
                        $operator = '!=';
                    }
                    // quote column
                    if (!$columnQuoted) {
                        $columnInfo = $this->disassembleField($column);
                        $columnAssembled = $columnInfo['assembled'];
                    } else {
                        $columnAssembled = $column;
                    }
                    // quote value
                    if (!$valueQuoted) {
                        $value = $this->quoteValue($value);
                    }
                    $assembled[] = "{$columnAssembled} {$operator} {$value}";
                } else if (
                    is_string($column) && in_array(strtolower(trim($column)), array('and', 'or'))
                    || (is_numeric($column) && is_array($value))
                ) {
                    // 3: 3.1 and 3.2 - recursion
                    if (is_numeric($column)) {
                        $subGlue = 'AND';
                    } else {
                        $subGlue = strtoupper(trim($column));
                    }
                    $assembled[] = '(' . $this->assembleConditions($value, $subGlue) . ')';
                } else {
                    throw new DbQueryException($this, 'DbQuery->assembleConditions(): what is this? ' . print_r(array('column' => $column, 'value' => $value), true));
                }
            }
            return implode(" $glue ", $assembled);
        } else {
            throw new DbQueryException($this, 'DbQuery->assembleConditions(): what is this? ' . print_r($conditions, true));
        }
    }

    /**
     * Process records from statement and return required amount of records
     * Note: when limit == 1 returns single record, not an array with 1 record
     * @param \PDOStatement $statement
     * @param string $type - type of query: 'all', 'first', expression, integer
     *      'all' and 'first': same query with different limits
     *      expression and integer: only 1 field and 1 record fetched
     * @param bool $withRootTableAlias -
     *      true: result will look like array(0 => array('RootAlias' => array('column1' => 'value1', ...), 1 => array(...), ...)
     *      false: result will look like array(0 => array('column1' => 'value1', ...), 1 => array(...), ...)
     * @return array[]|array|string|int|float
     */
    protected function processRecords(\PDOStatement $statement, $type = 'all', $withRootTableAlias = true) {
        if ($statement && $statement->rowCount() > 0) {
            $type = strtolower($type);
            if (!in_array($type, array('all', 'first'))) {
                $value = $statement->fetchColumn();
                return $value;
            } else {
                $records = $statement->fetchAll(\PDO::FETCH_ASSOC);
                if ($type == 'first') {
                    $records = array_slice($records, 0, 1);
                }
                $return = array();
                foreach ($records as $record) {
                    $nested = array();
                    // process record's column aliases and group column values by table alias
                    foreach ($record as $colAlias => $value) {
                        if (preg_match('%^__(.+?)__(.+?)$%is', $colAlias, $colInfo)) {
                            list(, $tableAlias, $column) = $colInfo;
                            if (empty($nested[$tableAlias])) {
                                $nested[$tableAlias] = array();
                            }
                            $nested[$tableAlias][$column] = $value;
                        } else {
                            $nested[$colAlias] = $value;
                        }
                    }
                    // make record nested + add missing child records
                    foreach ($this->aliasToTable as $tableAlias => $table) {
                        if ($tableAlias != $this->alias) {
                            if (!empty($nested[$tableAlias])) {
                                $nested[$this->alias][$tableAlias] = $nested[$tableAlias];
                                unset($nested[$tableAlias]);
                            } else {
                                $nested[$this->alias][$tableAlias] = array();
                            }
                        }
                    }
                    if (!$withRootTableAlias && isset($nested[$this->alias])) {
                        $nested = $nested[$this->alias];
                    }
                    $return[] = $nested;
                }
                return $type == 'first' ? $return[0] : $return;
            }
        } else {
            return array();
        }
    }
}