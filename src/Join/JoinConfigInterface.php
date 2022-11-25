<?php

declare(strict_types=1);

namespace PeskyORM\Join;

interface JoinConfigInterface
{
    public const JOIN_LEFT = 'left';
    public const JOIN_RIGHT = 'right';
    public const JOIN_INNER = 'inner';
    public const JOIN_FULL = 'full';
    public const JOIN_CROSS = 'cross';

    /**
     * Get JOIN name (alias)
     * Examples:
     * {join_type} JOIN {foreign_table_schema_and_name} AS {join_name} ...
     * CROSS JOIN {query} AS {join_name} ...
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