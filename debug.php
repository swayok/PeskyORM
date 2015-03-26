<?php

// debug
/// Debug print
function dpr() {
    if (isDebugAllowed()) {
        echo '<DIV style="position:relative; z-index:200; padding-left:10px; background-color:#CCCCCC; color:#000000; text-align:left;">';
        echo "\n<BR/>";
        echo '<PRE style="white-space: pre-wrap">';
        echo _dprPlain(func_get_args(), debug_backtrace(false));
        echo '</PRE>';
        echo '</DIV>';
        flush();
    }
}

function dprf() {
    echo '<DIV style="position:relative; z-index:200; padding-left:10px; background-color:#CCCCCC; color:#000000; text-align:left;">';
    echo "\n<BR/>";
    echo '<PRE>';
    echo _dprPlain(func_get_args(), debug_backtrace(false));
    echo '</PRE>';
    echo '</DIV>';
    flush();
}

function isDebugAllowed() {
    return !defined('DEBUG_PRINT_ENABLED') || DEBUG_PRINT_ENABLED || (defined('EXCEPTION_REPORT') && EXCEPTION_REPORT);
}

function dprPlain() {
    if (isDebugAllowed()) {
        echo _dprPlain(func_get_args(), debug_backtrace(false));
    }
}

function _dprPlain($args, $a) {
    if (!isDebugAllowed()) {
        return '';
    }
    return _dprPlainForced($args, $a);
}

function _dprPlainForced($args, $a) {
    ob_start('htmlspecialchars');
    if (isset($a[0])) {
        echo $a[0]['file'] . ' line ' . $a[0]['line'] . ' ';
    }
    if (isset($a[1])) {
        if (isset($a[1]['class'])) {
            echo $a[1]['class'] . '::';
        }
        if (isset($a[1]['function'])) {
            echo $a[1]['function'] . '()';
        }
    }
    echo "\n";
    if (count($args)) {
        foreach ($args as $arg) {
            if (is_bool($arg)) {
                $arg = $arg ? 'true' : 'false';
            } else if ($arg === null) {
                $arg = 'null';
            } else if ($arg === '') {
                $arg = '*empty string*';
            } else if ($arg === array()) {
                $arg = '*empty array*';
            } else if (empty($arg) && $arg != '0') {
                $arg = '*empty object*';
            }
            echo "\n > ";
            print_r($arg);
            echo "\n";
        }
    } else {
        echo 'dpr: no args passed';
    }
    $ret = ob_get_contents();
    ob_end_clean();
    return $ret;
}

function bla() {
    call_user_func_array('dpr', func_get_args());
}

function derp() {
    call_user_func_array('dpr', func_get_args());
}

function ffs() {
    call_user_func_array('dpr', func_get_args());
}

function dprToStr() {
    if (!defined('DEBUG_PRINT_ENABLED') || DEBUG_PRINT_ENABLED || (defined('EXCEPTION_REPORT') && EXCEPTION_REPORT)) {
        $ret = '';
        if (func_num_args()) {
            $data = print_r(func_get_args(), true);
            $ret .= "\n<PRE>\n" . $data . "\n</PRE>\n";
        }
        return $ret;
    }
    return '';
}

// debug backtrace
function dbt($returnString = false, $printObjects = false, $htmlFormat = true) {
    if (!defined('DEBUG_PRINT_ENABLED') || DEBUG_PRINT_ENABLED || (defined('EXCEPTION_REPORT') && EXCEPTION_REPORT) || $returnString) {
        $debug = debug_backtrace($printObjects);
        $ret = array();
        $log = array();
        $file = 'unknown';
        $lineNum = '?';
        foreach ($debug as $index => $line) {
            if (!isset($line['file'])) {
                $line['file'] = '(probably) ' . $file;
                $line['line'] = '(probably) ' . $lineNum;
            } else {
                $file = $line['file'];
                $lineNum = $line['line'];
            }
            if (isset($line['class'])) {
                $function = $line['class'] . $line['type'] . $line['function'];
            } else {
                $function = $line['function'];
            }
            if (isset($line['args'])) {
                if (is_array($line['args'])) {
                    $args = array();
                    foreach ($line['args'] as $arg) {
                        if (is_array($arg)) {
                            $args[] = 'Array';
                        } else if (is_object($arg)) {
                            $args[] = get_class($arg);
                        } else if (is_null($arg)) {
                            $args[] = 'null';
                        } else if ($arg === false) {
                            $args[] = 'false';
                        } else if ($arg === true) {
                            $args[] = 'true';
                        } else if (is_string($arg) && strlen($arg) > 200) {
                            $args[] = substr($arg, 0, 200);
                        } else {
                            $args[] = $arg;
                        }
                    }
                    $line['args'] = implode(' , ', $args);
                }
            } else {
                $line['args'] = '';
            }
            if ($htmlFormat) {
                $ret[] = '<b>#' . $index . '</b> [<font color="#FF7777">' . $line['file'] . '</font>]:' . $line['line'] . ' ' . $function . '(' . htmlentities($line['args']) . ')';
            } else {
                $ret[] = '#' . $index . ' [' . $line['file'] . ']:' . $line['line'] . ' ' . $function . '(' . $line['args'] . ')';
            }
            $log[] = '#' . $index . ' [' . $line['file'] . ']:' . $line['line'] . ' ' . $function . '(' . $line['args'] . ')';
        }
        if ($htmlFormat) {
            $ret = '<DIV style="position:relative; z-index:200; padding-left:10px; background-color:#DDDDDD; color:#000000; text-align:left;">' . implode('<br/>', $ret) . '</div><hr/>';
        } else {
            $ret = "\n" . implode("\n", $ret) . "\n";
        }

        if ($returnString) {
            return $ret;
        } else {
            echo $ret;
        }
    } else if ($returnString) {
        return '';
    }
    return '';
}

// for log files

if (!defined('LOGS')) {
    define('LOGS', __DIR__ . DIRECTORY_SEPARATOR);
}


class dpr_static {
    public static $count = 0;
    public static $logFile = 'dpr.log';
    public static $defaultLogFile = 'dpr.log';
    public static $startTime = 0;
    public static $scriptLoaded = false;
}

function logItSetFile($fileName = null) {
    if ($fileName !== null) {
        dpr_static::$logFile = $fileName;
        $dir = dirname(LOGS . $fileName);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    } else {
        dpr_static::$logFile = dpr_static::$defaultLogFile;
    }
}

function logItStart() {
    if (func_num_args()) {
        $data = print_r(func_get_args(), true);
        file_put_contents(LOGS . dpr_static::$logFile, $data . "\n\n");
    }
}

function logIt() {
    if (func_num_args()) {
        $data = print_r(func_get_args(), true);
        file_put_contents(LOGS . dpr_static::$logFile, $data . "\n\n", FILE_APPEND);
    }
}
