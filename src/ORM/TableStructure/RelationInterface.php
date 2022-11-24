<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\Join\JoinConfigInterface;
use PeskyORM\Join\NormalJoinConfigInterface;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Table\TableInterface;

interface RelationInterface
{

    public const HAS_ONE = 'has_one';
    public const HAS_MANY = 'has_many';
    public const BELONGS_TO = 'belongs_to';

    public const JOIN_LEFT = JoinConfigInterface::JOIN_LEFT;
    public const JOIN_RIGHT = JoinConfigInterface::JOIN_RIGHT;
    public const JOIN_INNER = JoinConfigInterface::JOIN_INNER;
    public const JOIN_FULL = JoinConfigInterface::JOIN_FULL;

    /**
     * Name of relation and it's alias in DB queries
     */
    public function getName(): string;

    public function getType(): string;

    /**
     * Name of the column in local/main table
     */
    public function getColumnName(): string;

    public function getForeignTable(): TableInterface;

    public function getForeignColumnName(): string;

    public function getJoinType(): string;

    public function getDisplayColumnName(): string|\Closure|null;

    public function getAdditionalJoinConditions(
        TableInterface $sourceTable,
        ?string $sourceTableAlias,
        bool $forStandaloneSelect,
        ?RecordInterface $localRecord = null
    ): array;

    public function toJoinConfig(
        TableInterface $sourceTable,
        ?string $sourceTableAlias = null,
        ?string $overrideJoinName = null,
        ?string $overrideJoinType = null
    ): NormalJoinConfigInterface;
}