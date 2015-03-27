<?php

require_once 'error_handling.php';
require_once 'debug.php';

spl_autoload_register(function ($class) {

    // project-specific namespace prefix
    $prefix = 'PeskyORM\\';

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
        require_once $file;
    }
});

$config = \PeskyORM\DbConnectionConfig::create()
    ->setDriver(\PeskyORM\DbConnectionConfig::POSTGRESQL)
    ->setDbName('cmroaddb')
    ->setUserName('test')
    ->setPassword('test');

\PeskyORM\DbModel::setDbConnectionConfig($config, 'default');
\PeskyORM\Db::$collectAllQueries = true;

$user = \PeskyORM\Model\AppModel::getDbObject('User')->find(array('id' => 2241));



dpr($user->toPublicArray());
foreach ($user->UserToken as $token) {
    dpr($token->toPublicArray());
}


dpr(\PeskyORM\Db::getAllQueries());