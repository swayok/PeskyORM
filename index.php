<?php

error_reporting(E_ALL);
ini_set('track_errors', 1);
ini_set('html_errors', 1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

spl_autoload_register(function ($class) {

    // project-specific namespace prefix
    $prefix = 'ORM\\';

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/src/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

$config = array(
    'host' => 'localhost',
    'driver' => 'pgsql',
    'database' => 'cmroaddb',
    'user' => 'test',
    'password' => 'test',
);

$db = new \ORM\Db($config['driver'], $config['database'], $config['user'], $config['password'], $config['host']);
