<?php

declare(strict_types=1);


namespace PeskyORM\Tests\PeskyORMTest\Adapter;

use PeskyORM\Core\DbAdapter;

class OtherAdapterTesting extends DbAdapter
{

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
}