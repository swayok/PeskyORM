<?php

declare(strict_types=1);

namespace PeskyORM\Core;

interface JoinConfigInterface
{
    public const JOIN_LEFT = 'left';
    public const JOIN_RIGHT = 'right';
    public const JOIN_INNER = 'inner';
    public const JOIN_FULL = 'full';
    public const JOIN_CROSS = 'cross';

    /**
     * Get JOIN name (alias)
     * Example: INNER JOIN foreign_table_schema.foreign_table_name as ForeignTableAlias ON ($conditions) AS $joinName
     */
    public function getJoinName(): ?string;

    /**
     * Get join type (one of self::JOIN_*)
     */
    public function getJoinType(): ?string;

    /**
     * Check if JOIN configuration has everything needed to assemble JOIN for DB query
     */
    public function isValid(): bool;
}