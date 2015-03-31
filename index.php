<?php

require_once 'error_handling.php';
require_once 'debug.php';
require_once 'dBug.php';

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

$model = \PeskyORM\Model\UserModel::getInstance();
$user = \PeskyORM\Object\User::create($model);

//$user->find(array('id' => 1));
//new dBug($user->UserToken);

$testEmail = 'qqqqqqq@gmail.com';
$model->delete(array('email' => $testEmail));

$user
    ->setEmail($testEmail)
    ->setPassword(sha1('test'))
    ->save();

$user->setConfirmed(true)
    ->setCycleRecording(true);

new dBug($user);

$user->rollback();

new dBug($user);

dpr($user->exists(), $user->toPublicArray(), $user->getValidationErrors());

//dpr($user->toPublicArray(null, array('UserToken'), true));
/*foreach ($user->UserToken as $token) {
    dpr($token->toPublicArray());
}*/


dpr(\PeskyORM\Db::getAllQueries());