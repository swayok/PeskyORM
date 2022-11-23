<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

abstract class DbQueryException extends DbException
{
    protected string $query;

    public function __construct(string $message, string $query, int $code)
    {
        $this->query = $query;
        parent::__construct($this->modifyMessage($message), $code);
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    protected function modifyMessage(string $message): string
    {
        return rtrim($message, '.') . '. Query: ' . $this->query;
    }
}