<?php

declare(strict_types=1);

namespace PeskyORM\Profiling;

use JetBrains\PhpStorm\ArrayShape;

abstract class PdoProfilingHelper
{
    /**
     * @var TraceablePDO[]
     */
    protected static array $connections = [];
    
    /**
     * Adds a new PDO instance to be profiled
     * @param TraceablePDO $pdo
     * @param string $name - connection name or some id
     */
    public static function addConnection(TraceablePDO $pdo, string $name): void
    {
        static::$connections[$name] = $pdo;
    }

    /**
     * Remove PDO instance by name
     */
    public static function removeConnection(string $name): void
    {
        unset(static::$connections[$name]);
    }
    
    /**
     * Returns PDO instances to be profiled
     * @return TraceablePDO[]
     */
    public static function getConnections(): array
    {
        return static::$connections;
    }

    public static function forgetConnections(): void
    {
        static::$connections = [];
    }

    #[ArrayShape([
        'statements_count' => 'int',
        'failed_statements_count' => 'int',
        'accumulated_duration' => 'float',
        'max_memory_usage' => 'float',
        'statements' => 'array'
    ])]
    public static function collect(): array
    {
        $data = [
            'statements_count' => 0,
            'failed_statements_count' => 0,
            'accumulated_duration' => 0,
//            'accumulated_memory_usage' => 0,
            'max_memory_usage' => 0,
            'statements' => [],
        ];
        
        foreach (static::$connections as $name => $pdo) {
            $pdodata = static::collectPDO($pdo);
            $data['statements_count'] += $pdodata['statements_count'];
            $data['failed_statements_count'] += $pdodata['failed_statements_count'];
            $data['accumulated_duration'] += $pdodata['accumulated_duration'];
//            $data['accumulated_memory_usage'] += $pdodata['accumulated_memory_usage'];
            $data['max_memory_usage'] = max($data['max_memory_usage'], $pdodata['max_memory_usage']);
            $data['statements'][$name] = $pdodata['statements'];
        }
        
        return $data;
    }
    
    /**
     * Collects data from a single TraceablePDO instance
     *
     * @param TraceablePDO $pdo
     * @return array
     */
    protected static function collectPDO(TraceablePDO $pdo): array
    {
        $stmts = [];
        foreach ($pdo->getExecutedStatements() as $stmt) {
            $stmts[] = [
//                'sql' => $this->renderSqlWithParams ? $stmt->getSqlWithParams() : $stmt->getSql(),
                'sql' => $stmt->getSql(),
                'row_count' => $stmt->getRowCount(),
                'prepared_statement_id' => $stmt->getPreparedId(),
//                'prepared_statement' => $stmt->getSql(),
                'params' => $stmt->getParameters(),
                'duration' => $stmt->getDuration(),
                'memory_before' => $stmt->getStartMemory(),
                'memory_used' => $stmt->getMemoryUsage(),
                'memory_after' => $stmt->getEndMemory(),
                'is_success' => $stmt->isSuccess(),
                'error_code' => $stmt->getErrorCode(),
                'error_message' => $stmt->getErrorMessage(),
                'started_at' => $stmt->getStartTime(),
                'ended_at' => $stmt->getEndTime(),
            ];
        }
        
        return [
            'statements_count' => count($stmts),
            'failed_statements_count' => count($pdo->getFailedExecutedStatements()),
            'accumulated_duration' => $pdo->getAccumulatedStatementsDuration(),
//            'accumulated_memory_usage' => $pdo->getMemoryUsage(),
            'max_memory_usage' => $pdo->getPeakMemoryUsage(),
            'statements' => $stmts,
        ];
    }
    
    protected static function formatDuration(float $seconds): string
    {
        if ($seconds < 0.001) {
            return round($seconds * 1000000) . 'μs';
        }

        if ($seconds < 1) {
            return round($seconds * 1000, 2) . 'ms';
        }

        return round($seconds, 2) . 's';
    }
    
    protected static function formatBytes(int $size, int $precision = 2): string
    {
        if ($size === 0) {
            return '0B';
        }
        
        $sign = $size < 0 ? '-' : '';
        $size = (int)abs($size);
        
        $base = log($size) / log(1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        return $sign . round(1024 ** ($base - floor($base)), $precision) . $suffixes[(int)floor($base)];
    }
}