<?php

namespace PeskyORM\Profiling;

use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbAdapterInterface;

class PeskyOrmPdoProfiler
{
    
    /**
     * @var TraceablePDO[]
     */
    static protected $connections = [];
    
    /**
     * Init PDO profiling
     */
    static public function init()
    {
        DbAdapter::setConnectionWrapper(function (DbAdapterInterface $adapter, \PDO $pdo) {
            $name = $adapter->getConnectionConfig()
                    ->getName() . ' (DB: ' . $adapter->getConnectionConfig()
                    ->getDbName() . ')';
            return new TraceablePDO($pdo, $name);
        });
    }
    
    /**
     * Adds a new PDO instance to be collector
     *
     * @param TraceablePDO $pdo
     * @param string $name Optional connection name
     */
    static public function addConnection(TraceablePDO $pdo, $name = null)
    {
        if ($name === null) {
            $name = spl_object_hash($pdo);
        }
        static::$connections[$name] = $pdo;
    }
    
    /**
     * Returns PDO instances to be collected
     *
     * @return TraceablePDO[]
     */
    static public function getConnections()
    {
        return static::$connections;
    }
    
    /**
     * @return array
     */
    static public function collect()
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
    static protected function collectPDO(TraceablePDO $pdo)
    {
        $stmts = [];
        /** @var TracedStatement $stmt */
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
    
    /**
     * @param float $seconds
     * @return string
     */
    static public function formatDuration($seconds)
    {
        if ($seconds < 0.001) {
            return round($seconds * 1000000) . 'μs';
        } elseif ($seconds < 1) {
            return round($seconds * 1000, 2) . 'ms';
        }
        
        return round($seconds, 2) . 's';
    }
    
    /**
     * @param int|float $size
     * @param int $precision
     * @return string
     */
    static public function formatBytes($size, $precision = 2)
    {
        if ($size === 0 || $size === null) {
            return '0B';
        }
        
        $sign = $size < 0 ? '-' : '';
        $size = abs($size);
        
        $base = log($size) / log(1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        return $sign . round(1024 ** ($base - floor($base)), $precision) . $suffixes[(int)floor($base)];
    }
}