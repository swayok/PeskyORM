<?php

declare(strict_types=1);

namespace PeskyORM\Profiling;

/**
 * Holds information about a statement
 */
class TracedStatement
{
    protected string $sql;
    
    protected ?int $rowCount = null;
    protected array $parameters;
    
    protected ?float $startTime = null;
    protected ?float $endTime = null;
    protected ?float $duration = null;
    
    protected ?int $startMemory = null;
    protected ?int $endMemory = null;
    protected ?int $memoryDelta = null;
    
    protected ?string $preparedId = null;
    
    protected ?\Throwable $exception = null;
    
    public function __construct(string $sql, array $params = [], ?string $preparedId = null)
    {
        $this->sql = $sql;
        $this->parameters = $this->checkParameters($params);
        $this->preparedId = $preparedId;
    }
    
    public function start(?float $startTime = null, ?float $startMemory = null): void
    {
        $this->startTime = $startTime ?: microtime(true);
        $this->startMemory = $startMemory ?: memory_get_usage(false);
    }
    
    public function end(?\Throwable $exception = null, int $rowCount = 0, ?float $endTime = null, ?int $endMemory = null): void
    {
        $this->endTime = $endTime ?: microtime(true);
        $this->duration = $this->endTime - $this->startTime;
        $this->endMemory = $endMemory ?: memory_get_usage(false);
        $this->memoryDelta = $this->endMemory - $this->startMemory;
        $this->exception = $exception;
        $this->rowCount = $rowCount;
    }
    
    /**
     * Check parameters for illegal (non UTF-8) strings, like Binary data.
     */
    public function checkParameters(array $params): array
    {
        foreach ($params as &$param) {
            if (!mb_check_encoding($param, 'UTF-8')) {
                $param = '[BINARY DATA]';
            }
        }
        
        return $params;
    }
    
    /**
     * Returns the SQL string used for the query
     */
    public function getSql(): string
    {
        return $this->sql;
    }
    
    /**
     * Returns the SQL string with any parameters used embedded
     */
    public function getSqlWithParams(string $quotationChar = '<>'): string
    {
        if (($l = strlen($quotationChar)) > 1) {
            $quoteLeft = substr($quotationChar, 0, $l / 2);
            $quoteRight = substr($quotationChar, $l / 2);
        } else {
            $quoteLeft = $quoteRight = $quotationChar;
        }
        
        $sql = $this->sql;
        foreach ($this->parameters as $k => $v) {
            $v = "$quoteLeft$v$quoteRight";
            if (!is_numeric($k)) {
                $sql = preg_replace("/{$k}\b/", $v, $sql, 1);
            } else {
                $p = strpos($sql, '?');
                $sql = substr($sql, 0, $p) . $v . substr($sql, $p + 1);
            }
        }
        
        return $sql;
    }
    
    /**
     * Returns the number of rows affected/returned
     */
    public function getRowCount(): int
    {
        return $this->rowCount;
    }
    
    /**
     * Returns an array of parameters used with the query
     */
    public function getParameters(): array
    {
        $params = [];
        foreach ($this->parameters as $name => $param) {
            $params[$name] = htmlentities($param, ENT_QUOTES, 'UTF-8', false);
        }
        
        return $params;
    }
    
    /**
     * Returns the prepared statement id
     */
    public function getPreparedId(): ?string
    {
        return $this->preparedId;
    }
    
    /**
     * Checks if this is a prepared statement
     */
    public function isPrepared(): bool
    {
        return $this->preparedId !== null;
    }
    
    public function getStartTime(): float
    {
        return $this->startTime;
    }
    
    public function getEndTime(): float
    {
        return $this->endTime;
    }
    
    /**
     * Returns the duration in seconds of the execution
     */
    public function getDuration(): float
    {
        return $this->duration;
    }
    
    public function getStartMemory(): int
    {
        return $this->startMemory;
    }
    
    public function getEndMemory(): int
    {
        return $this->endMemory;
    }
    
    /**
     * Returns the memory usage during the execution
     */
    public function getMemoryUsage(): int
    {
        return $this->memoryDelta;
    }
    
    /**
     * Checks if the statement was successful
     */
    public function isSuccess(): bool
    {
        return $this->exception === null;
    }
    
    /**
     * Returns the exception triggered
     */
    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
    
    /**
     * Returns the exception's code
     */
    public function getErrorCode(): int
    {
        return $this->exception !== null ? $this->exception->getCode() : 0;
    }
    
    /**
     * Returns the exception's message
     */
    public function getErrorMessage(): string
    {
        return $this->exception !== null ? $this->exception->getMessage() : '';
    }
}