<?php

declare(strict_types=1);


namespace PeskyORM\Tests\PeskyORMTest\Adapter;

use PeskyORM\Adapter\DbAdapterAbstract;
use PeskyORM\Config\Connection\DbConnectionConfigInterface;

class OtherAdapterTesting extends DbAdapterAbstract
{

    public function __construct(protected DbConnectionConfigInterface $config)
    {
    }

    protected function getConditionAssembler(string $operator): ?\Closure
    {
        return null;
    }

    public function listen(
        string $channel,
        \Closure $handler,
        int $sleepIfNoNotificationMs = 1000,
        int $sleepAfterNotificationMs = 0
    ): void {
    }

    public function setTimezone(string $timezone): static
    {
        return $this;
    }

    public function setSearchPath(string $newSearchPath): static
    {
        return $this;
    }

    public function quoteJsonSelectorExpression(array $sequence): string
    {
        return '';
    }

    public function isDbSupportsTableSchemas(): bool
    {
        return false;
    }

    public function getDefaultTableSchema(): ?string
    {
        return null;
    }

    public function addDataTypeCastToExpression(string $dataType, string $expression): string
    {
        return $expression;
    }

    public function hasTable(string $table, ?string $schema = null): bool
    {
        return false;
    }

    public function getConnectionConfig(): DbConnectionConfigInterface
    {
        return $this->config;
    }

    protected function resolveInsertOneQueryWithReturningColumns(
        string $insertQuery,
        string $table,
        array $data,
        array $dataTypes,
        array $returning,
        string $pkName
    ): array {
        return [];
    }

    protected function resolveInsertManyQueryWithReturningColumns(
        string $insertQuery,
        string $table,
        array $columns,
        array $data,
        array $dataTypes,
        array $returning,
        string $pkName
    ): array {
        return [];
    }

    protected function resolveUpdateQueryWithReturningColumns(
        string $updateQuery,
        string $assembledConditions,
        string $table,
        array $updates,
        array $dataTypes,
        array $returning,
        string $pkName
    ): array {
        return [];
    }

    protected function resolveDeleteQueryWithReturningColumns(
        string $deleteQuery,
        string $assembledConditions,
        string $table,
        array $returning,
        string $pkName
    ): array {
        return [];
    }
}