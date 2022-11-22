<?php

declare(strict_types=1);

namespace PeskyORM\Profiling;

interface TransactionsTracingInterface
{
    public function setTransactionsTracing(bool $enable = true): void;

    public function isTransactionsTracingEnabled(): bool;

    public function getTransactionsTraces(): array;
}