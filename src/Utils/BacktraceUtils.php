<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

use JetBrains\PhpStorm\ArrayShape;

abstract class BacktraceUtils
{
    /**
     * @param bool $withObjects - backtrace will contain information about objects passed to functions/methods
     * @param int $ignoreSomeLastTraces - ignore some traces in the end (by default = 1 to ignore trace of this method)
     * @return array
     */
    public static function getBackTrace(
        bool $withObjects = false,
        int $ignoreSomeLastTraces = 1,
    ): array {
        $backtrace = debug_backtrace($withObjects ? DEBUG_BACKTRACE_PROVIDE_OBJECT : 0);
        if ($ignoreSomeLastTraces > 0) {
            $backtrace = array_slice($backtrace, 0, -$ignoreSomeLastTraces);
        }
        return $backtrace;
    }

    public static function convertBacktraceToString(array $backtrace): string
    {
        $ret = [];
        foreach ($backtrace as $index => $line) {
            $line = static::normalizeBacktraceItem($line);
            $ret[] = '#' . $index . ' [' . $line['file'] . ']:'
                . $line['line'] . ' ' . $line['function'] . '(' . $line['args'] . ')';
        }
        return implode("\n", $ret);
    }

    public static function normalizeBacktraceItems(array $backtrace): array
    {
        foreach ($backtrace as &$line) {
            $line = static::normalizeBacktraceItem($line);
        }
        return $backtrace;
    }

    #[ArrayShape([
        'line' => 'string',
        'file' => 'string',
        'function' => 'string',
        'args' => 'string',
        'class' => 'string|null',
        '_normalized' => 'bool',
    ])]
    private static function normalizeBacktraceItem(array $item): array
    {
        if (!empty($item['_normalized'])) {
            return $item;
        }

        $item['_normalized'] = true;
        if (!isset($item['file'])) {
            $item['file'] = '(unknown)';
            $item['line'] = '(unknown)';
        }
        if (isset($item['class'])) {
            $item['function'] = $item['class'] . $item['type'] . $item['function'];
        } elseif (!isset($item['function'])) {
            $item['function'] = '';
        }
        if (isset($item['args'])) {
            if (is_array($item['args'])) {
                $args = [];
                foreach ($item['args'] as $arg) {
                    if (is_array($arg)) {
                        $args[] = 'Array';
                    } elseif (is_object($arg)) {
                        $args[] = get_class($arg);
                    } elseif (is_null($arg)) {
                        $args[] = 'null';
                    } elseif ($arg === false) {
                        $args[] = 'false';
                    } elseif ($arg === true) {
                        $args[] = 'true';
                    } elseif (is_string($arg) && strlen($arg) > 200) {
                        $args[] = substr($arg, 0, 200);
                    } else {
                        $args[] = $arg;
                    }
                }
                $item['args'] = implode(' , ', $args);
            }
        } else {
            $item['args'] = '';
        }
        return $item;
    }
}