<?php

namespace PeskyORM\Profiling;

class PeskyOrmPdoProfiler {

    /**
     * @var TraceablePDO[]
     */
    static protected $connections;

    /**
     * Adds a new PDO instance to be collector
     *
     * @param TraceablePDO $pdo
     * @param string $name Optional connection name
     */
    static public function addConnection(TraceablePDO $pdo, $name = null) {
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
    static public function getConnections() {
        return static::$connections;
    }

    /**
     * @return array
     */
    static public function collect() {
        $data = [
            'statements_count' => 0,
            'failed_statements_count' => 0,
            'accumulated_duration' => 0,
            'memory_usage' => 0,
            'peak_memory_usage' => 0,
            'statements' => [],
        ];

        foreach (static::$connections as $name => $pdo) {
            $pdodata = static::collectPDO($pdo);
            $data['statements_count'] += $pdodata['statements_count'];
            $data['failed_statements_count'] += $pdodata['failed_statements_count'];
            $data['accumulated_duration'] += $pdodata['accumulated_duration'];
            $data['memory_usage'] += $pdodata['memory_usage'];
            $data['peak_memory_usage'] = max($data['peak_memory_usage'], $pdodata['peak_memory_usage']);
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $data['statements'][$name] = $pdodata['statements'];
        }

        $data['accumulated_duration_str'] = static::formatDuration($data['accumulated_duration']);
        $data['memory_usage_str'] = static::formatBytes($data['memory_usage']);
        $data['peak_memory_usage_str'] = static::formatBytes($data['peak_memory_usage']);

        return $data;
    }

    /**
     * Collects data from a single TraceablePDO instance
     *
     * @param TraceablePDO $pdo
     * @return array
     */
    static protected function collectPDO(TraceablePDO $pdo) {
        $stmts = [];
        /** @var TracedStatement $stmt */
        foreach ($pdo->getExecutedStatements() as $stmt) {
            $stmts[] = [
//                'sql' => $this->renderSqlWithParams ? $stmt->getSqlWithParams() : $stmt->getSql(),
                'sql' => $stmt->getSql(),
                'row_count' => $stmt->getRowCount(),
                'stmt_id' => $stmt->getPreparedId(),
//                'prepared_stmt' => $stmt->getSql(),
                'params' => (object)$stmt->getParameters(),
                'duration' => $stmt->getDuration(),
                'duration_str' => static::formatDuration($stmt->getDuration()),
                'memory' => $stmt->getMemoryUsage(),
                'memory_str' => static::formatBytes($stmt->getMemoryUsage()),
                'end_memory' => $stmt->getEndMemory(),
                'end_memory_str' => static::formatBytes($stmt->getEndMemory()),
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
//            'accumulated_duration_str' => static::formatDuration($pdo->getAccumulatedStatementsDuration()),
            'memory_usage' => $pdo->getMemoryUsage(),
//            'memory_usage_str' => static::formatBytes($pdo->getPeakMemoryUsage()),
            'peak_memory_usage' => $pdo->getPeakMemoryUsage(),
//            'peak_memory_usage_str' => static::formatBytes($pdo->getPeakMemoryUsage()),
            'statements' => $stmts,
        ];
    }

    /**
     * @param float $seconds
     * @return string
     */
    static public function formatDuration($seconds) {
        if ($seconds < 0.001) {
            return round($seconds * 1000000) . 'μs';
        } else if ($seconds < 1) {
            return round($seconds * 1000, 2) . 'ms';
        }

        return round($seconds, 2) . 's';
    }

    /**
     * @param int|float $size
     * @param int $precision
     * @return string
     */
    static public function formatBytes($size, $precision = 2) {
        if ($size === 0 || $size === null) {
            return '0B';
        }

        $sign = $size < 0 ? '-' : '';
        $size = abs($size);

        $base = log($size) / log(1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return $sign . round(pow(1024, $base - floor($base)), $precision) . $suffixes[(int)floor($base)];
    }
}