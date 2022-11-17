<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

use PeskyORM\Core\Utils\BacktraceUtils;

class DbTransactionException extends DbException
{
    protected array $transactionsBacktraces;

    public function __construct(
        string $message,
        int $code,
        array $transactionsBacktraces = []
    ) {
        $this->transactionsBacktraces = $transactionsBacktraces;
        $backtraces = $this->getTransactionsBacktracesAsString();
        parent::__construct($message . ($backtraces === '' ? '' : ': ' . $backtraces), $code);
    }

    public function getTransactionsBacktraces(): array
    {
        return $this->transactionsBacktraces;
    }

    public function getTransactionsBacktracesAsString(): string
    {
        $backtraces = '';
        foreach ($this->transactionsBacktraces as $trace) {
            $backtraces .= "\n" . BacktraceUtils::convertBacktraceToString($trace);
        }
        return $backtraces;
    }
}