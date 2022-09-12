<?php

namespace PeskyORM\Profiling;

/**
 * Holds information about a statement
 */
class TracedStatement
{
    
    /** @var string */
    protected $sql;
    
    /** @var int|null */
    protected $rowCount;
    
    /** @var array */
    protected $parameters;
    
    /** @var float|null */
    protected $startTime;
    
    /** @var float|null */
    protected $endTime;
    
    /** @var float|null */
    protected $duration;
    
    /** @var int|null */
    protected $startMemory;
    
    /** @var int|null */
    protected $endMemory;
    
    /** @var int|null */
    protected $memoryDelta;
    
    /** @var string|null */
    protected $preparedId;
    
    /** @var \Throwable|null */
    protected $exception;
    
    /**
     * @param string $sql
     * @param array $params
     * @param string|null $preparedId
     */
    public function __construct(string $sql, array $params = [], ?string $preparedId = null)
    {
        $this->sql = $sql;
        $this->parameters = $this->checkParameters($params);
        $this->preparedId = $preparedId;
    }
    
    /**
     * @param float|null $startTime
     * @param float|null $startMemory
     */
    public function start(?float $startTime = null, ?float $startMemory = null)
    {
        $this->startTime = $startTime ?: microtime(true);
        $this->startMemory = $startMemory ?: memory_get_usage(false);
    }
    
    /**
     * @param \Throwable|null $exception
     * @param int $rowCount
     * @param float|null $endTime
     * @param int|null $endMemory
     */
    public function end(?\Throwable $exception = null, int $rowCount = 0, ?float $endTime = null, ?int $endMemory = null)
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
     *
     * @param array $params
     * @return array
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
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }
    
    /**
     * Returns the SQL string with any parameters used embedded
     *
     * @param string $quotationChar
     * @return string
     */
    public function getSqlWithParams($quotationChar = '<>'): string
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
     *
     * @return int
     */
    public function getRowCount(): int
    {
        return $this->rowCount;
    }
    
    /**
     * Returns an array of parameters used with the query
     *
     * @return array
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
     *
     * @return string|null
     */
    public function getPreparedId(): ?string
    {
        return $this->preparedId;
    }
    
    /**
     * Checks if this is a prepared statement
     *
     * @return boolean
     */
    public function isPrepared(): bool
    {
        return $this->preparedId !== null;
    }
    
    /**
     * @return float
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }
    
    /**
     * @return float
     */
    public function getEndTime(): float
    {
        return $this->endTime;
    }
    
    /**
     * Returns the duration in seconds of the execution
     *
     * @return float
     */
    public function getDuration(): float
    {
        return $this->duration;
    }
    
    /**
     * @return int
     */
    public function getStartMemory(): int
    {
        return $this->startMemory;
    }
    
    /**
     * @return int
     */
    public function getEndMemory(): int
    {
        return $this->endMemory;
    }
    
    /**
     * Returns the memory usage during the execution
     *
     * @return int
     */
    public function getMemoryUsage(): int
    {
        return $this->memoryDelta;
    }
    
    /**
     * Checks if the statement was successful
     *
     * @return boolean
     */
    public function isSuccess(): bool
    {
        return $this->exception === null;
    }
    
    /**
     * Returns the exception triggered
     *
     * @return \Throwable|null
     */
    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
    
    /**
     * Returns the exception's code
     *
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->exception !== null ? $this->exception->getCode() : 0;
    }
    
    /**
     * Returns the exception's message
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->exception !== null ? $this->exception->getMessage() : '';
    }
}