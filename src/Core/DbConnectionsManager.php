<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PeskyORM\Adapter\Mysql;
use PeskyORM\Adapter\Postgres;

class DbConnectionsManager
{
    
    public const ADAPTER_MYSQL = 'mysql';
    public const ADAPTER_POSTGRES = 'pgsql';
    
    private static array $adapters = [
        self::ADAPTER_MYSQL => Mysql::class,
        self::ADAPTER_POSTGRES => Postgres::class,
    ];
    
    /**
     * @var DbAdapter[]|DbAdapterInterface[]
     */
    private static array $connections = [];
    
    /**
     * Add custom DB adapter
     * @param $name
     * @param $className - class must implement \PeskyORM\Core\DbAdapterInterface
     * @throws \InvalidArgumentException
     */
    public static function addAdapter($name, $className)
    {
        if (!in_array(DbAdapterInterface::class, class_implements($className), true)) {
            throw new \InvalidArgumentException("Class [$className] must implement " . DbAdapterInterface::class . ' interface');
        }
        self::$adapters[$name] = $className;
    }
    
    /**
     * @param string $connectionName
     * @param string $adapterName
     * @param DbConnectionConfigInterface $connectionConfig
     * @param bool $ignoreDuplicate - true: will ignore duplicate connections
     * @return DbAdapter|DbAdapterInterface
     * @throws \InvalidArgumentException
     */
    public static function createConnection(
        $connectionName,
        $adapterName,
        DbConnectionConfigInterface $connectionConfig,
        $ignoreDuplicate = false
    ) {
        if (empty($adapterName) || !isset(self::$adapters[$adapterName])) {
            throw new \InvalidArgumentException("DB adapter with name [$adapterName] not found");
        }
        $connectionName = strtolower($connectionName);
        if (isset(self::$connections[$connectionName])) {
            if (!$ignoreDuplicate) {
                throw new \InvalidArgumentException("DB connection with name [$connectionName] already exists");
            }
        } else {
            self::$connections[$connectionName] = new self::$adapters[$adapterName]($connectionConfig);
        }
        return self::$connections[$connectionName];
    }
    
    /**
     * @param string $connectionName
     * @param array $connectionInfo
     *      - required keys: 'driver' or 'adapter' = 'mysql', 'pgsql', ... - any key in DbConnectionsManager::$adapters
     *      - optional keys (depends on driver): 'database', 'username', 'password', 'charset'
     *                                           'host', 'port', 'socket', 'options' (array)
     * @param bool $ignoreDuplicate - true: will ignore duplicate connections
     * @return DbAdapter|DbAdapterInterface
     * @throws \InvalidArgumentException
     */
    public static function createConnectionFromArray($connectionName, array $connectionInfo, $ignoreDuplicate = false)
    {
        if (empty($connectionInfo['driver']) && empty($connectionInfo['adapter'])) {
            throw new \InvalidArgumentException('$connectionInfo must contain a value for key \'driver\' or \'adapter\'');
        }
        $adapterName = empty($connectionInfo['adapter']) ? $connectionInfo['driver'] : $connectionInfo['adapter'];
        if (empty($adapterName) || !isset(self::$adapters[$adapterName])) {
            throw new \InvalidArgumentException("DB adapter with name [$adapterName] not found");
        }
        /** @var DbAdapterInterface $adapterClass */
        $adapterClass = static::$adapters[$adapterName];
        /** @var DbConnectionConfigInterface $configClass */
        $configClass = $adapterClass::getConnectionConfigClass();
        $connectionConfig = $configClass::fromArray($connectionInfo, $connectionName);
        return static::createConnection($connectionName, $adapterName, $connectionConfig, $ignoreDuplicate);
    }
    
    /**
     * Add alternative name for existing connection
     * @param string $connectionName
     * @param string $alternativeName
     * @param bool $ignoreDuplicate - true: will ignore duplicate connections
     * @throws \InvalidArgumentException
     */
    public static function addAlternativeNameForConnection($connectionName, $alternativeName, $ignoreDuplicate = false)
    {
        if (isset(self::$connections[$alternativeName]) && !$ignoreDuplicate) {
            throw new \InvalidArgumentException("DB connection with name [$alternativeName] already exists");
        }
        self::$connections[$alternativeName] = static::getConnection($connectionName);
    }
    
    /**
     * Get connection
     * @param string $connectionName
     * @return DbAdapter|DbAdapterInterface
     * @throws \InvalidArgumentException
     */
    public static function getConnection($connectionName)
    {
        if (!isset(self::$connections[$connectionName])) {
            throw new \InvalidArgumentException("DB connection with name [$connectionName] not found");
        }
        return self::$connections[$connectionName];
    }
    
    /**
     * @param string $connectionName
     * @return bool
     */
    public static function hasConnection($connectionName)
    {
        return isset(self::$connections[$connectionName]);
    }
    
    /**
     * Disconnect all adapters
     */
    public static function disconnectAll()
    {
        foreach (self::$connections as $adapter) {
            $adapter->disconnect();
        }
    }
    
    /**
     * @return DbAdapter[]|DbAdapterInterface[]
     */
    public static function getAll()
    {
        return self::$connections;
    }
    
}