<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\Join\JoinConfigInterface;
use PeskyORM\Join\NormalJoinConfigInterface;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Table\TableInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

/**
 * Relation will be converted to NormalJoinConfigInterface and result in next query format:
 * {join_type} JOIN {foreign_table_schema}.{foreign_table_name} AS {relation_name}
 *      ON {local_table_alias}.{local_column_name} = {relation_name}.{foreign_column_name}
 *      AND {additional_conditions}
 * @see NormalJoinConfigInterface
 */
interface RelationInterface
{
    public const HAS_ONE = 'has_one';
    public const HAS_MANY = 'has_many';
    public const BELONGS_TO = 'belongs_to';

    /**
     * Name of relation and it's alias in DB queries
     */
    public function getName(): string;

    public function setName(string $relationName): static;

    /**
     * Type of relation.
     * One of: self::HAS_ONE, self::HAS_MANY, self::BELONGS_TO
     */
    public function getType(): string;

    /**
     * Name of the column in local table
     */
    public function getLocalColumnName(): string;

    public function getForeignTable(): TableInterface;

    public function getForeignColumn(): TableColumnInterface;

    /**
     * Name of the column in foreign table
     */
    public function getForeignColumnName(): string;

    /**
     * Get join type.
     * One of JoinConfigInterface::JOIN_*
     * @see JoinConfigInterface
     */
    public function getJoinType(): string;

    /**
     * Get additional join conditions.
     * These are additinal conditions will be added to default condition.
     * @see NormalJoinConfigInterface::setAdditionalJoinConditions()
     */
    public function getAdditionalJoinConditions(
        bool $forStandaloneSelect,
        string $localTableAlias,
        ?RecordInterface $localRecord = null
    ): array;

    /**
     * @param string $localTableAlias - Local table alias used in DB queries.
     * @param string|null $overrideJoinName - override default join name (relation name)
     * @param string|null $overrideJoinType - override default join type. One of NormalJoinConfigInterface::JOIN_*
     * Example query: SELECT * FROM {local_table_name} AS {local_table_alias}
     * {join_type} JOIN ... AS {join_name|relation_name}
     * ON {join_name|relation_name}.{foreign_column_name} = {local_table_alias}.{local_column_name}
     * @return NormalJoinConfigInterface
     */
    public function toJoinConfig(
        string $localTableAlias,
        ?string $overrideJoinName = null,
        ?string $overrideJoinType = null
    ): NormalJoinConfigInterface;
}