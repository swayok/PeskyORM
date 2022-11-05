<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

use PDO;
use PDOStatement;

class DetailedPDOException extends DbException
{

    protected ?string $originalMessage = null;

    public function __construct(
        protected PDOStatement|PDO $connectionOrStatement,
        protected string $query,
        ?\PDOException $previous = null
    ) {
        [, $code, $message] = $this->connectionOrStatement->errorInfo();
        $this->originalMessage = $message;
        if ($message === null) {
            // there was no error
            parent::__construct('', 0, $previous);
        } else {
            if (preg_match('%syntax error at or near "\$\d+"%i', $message)) {
                $message .= "\nNOTE: PeskyORM does not use prepared statements."
                    . " You possibly used one of PostgreSQL jsonb opertaors - '?', '?|' or '?&'."
                    . " You should use escaped operators ('??', '??|' or '??&') or functions:"
                    . " jsonb_exists(jsonb, text), jsonb_exists_any(jsonb, text)"
                    . " or jsonb_exists_all(jsonb, text) respectively";
            }
            $message .= ". \nQuery: " . $this->query;
            parent::__construct($message, $code, $previous);
        }
    }

    public function getConnectionOrStatement(): PDO|PDOStatement
    {
        return $this->connectionOrStatement;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return string|null
     */
    public function getOriginalMessage(): ?string
    {
        return $this->originalMessage;
    }
}