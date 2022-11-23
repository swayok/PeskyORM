<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

class DbQueryReturningValuesException extends DbQueryException
{
    protected ?string $selectQuery;

    public function __construct(string $message, string $mainQuery, ?string $selectQuery = null)
    {
        $this->selectQuery = $selectQuery;
        parent::__construct($message, $mainQuery, static::CODE_RETURNING_FAILED);
    }

    public function getSelectQuery(): ?string
    {
        return $this->selectQuery;
    }

    protected function modifyMessage(string $message): string
    {
        $message = rtrim($message, '.') . '. Main query: ' . $this->query;
        if ($this->selectQuery) {
            $message .= '. Select query: ' . $this->selectQuery;
        }
        return $message;
    }
}