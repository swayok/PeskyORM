<?php

namespace PeskyORM\Lib;

class Utils {

    static public function printToStr() {
        $ret = '';
        if (func_num_args()) {
            $data = print_r(func_get_args(), true);
            $ret .= "\n<PRE>\n" . $data . "\n</PRE>\n";
        }
        return $ret;
    }

    static public function getBackTrace($returnString = false, $printObjects = false, $htmlFormat = true) {
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
    }

    static public function jsonEncodeCyrillic($data) {
        if (floatval(phpversion()) >= 5.4) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            $json = json_encode($data);
            $json = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function ($match) {
                return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
            }, $json);
        }
        return $json;
    }
}