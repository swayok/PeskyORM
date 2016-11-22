<?php

namespace PeskyORM\Core;

class DbConnectionsManager {

    const ADAPTER_MYSQL = 'mysql';
    const ADAPTER_POSTGRES = 'pgsql';

    static private $adapters = [
        self::ADAPTER_MYSQL => '\PeskyORM\Adapter\Mysql',
        self::ADAPTER_POSTGRES => '\PeskyORM\Adapter\Postgres'
    ];

    /**
     * @var DbAdapter[]|DbAdapterInterface[]
     */
    static private $connections = [];

    /**
     * @var null|string $queryString
     */
    protected $lastQuery = null;

    /**
     * Add custom DB adapter
     * @param $name
     * @param $className - class must implement \PeskyORM\Core\DbAdapterInterface
     * @throws \InvalidArgumentException
     */
    static public function addAdapter($name, $className) {
        if (!in_array(DbAdapterInterface::class, class_implements($className), true)) {
            throw new \InvalidArgumentException("Class [$className] must implement " . DbAdapterInterface::class . ' interface');
        }
        self::$adapters[$name] = $className;
    }

    /**
     * @param string $connectionName
     * @param string $adapterName
     * @param DbConnectionConfigInterface $connectionConfig
     * @return DbAdapter|DbAdapterInterface
     * @throws \InvalidArgumentException
     */
    static public function createConnection(
        $connectionName,
        $adapterName,
        DbConnectionConfigInterface $connectionConfig
    ) {
        if (empty($adapterName) || !isset(self::$adapters[$adapterName])) {
            throw new \InvalidArgumentException("DB adapter with name [$adapterName] not found");
        }
        $connectionName = strtolower($connectionName);
        if (isset(self::$connections[$connectionName])) {
            throw new \InvalidArgumentException("DB connection with name [$connectionName] already exists");
        }
        self::$connections[$connectionName] = new self::$adapters[$adapterName]($connectionConfig);
        return self::$connections[$connectionName];
    }

    /**
     * Get connection
     * @param string $connectionName
     * @return DbAdapter|DbAdapterInterface
     * @throws \InvalidArgumentException
     */
    static public function getConnection($connectionName) {
        if (!isset(self::$connections[$connectionName])) {
            throw new \InvalidArgumentException("DB connection with name [$connectionName] not found");
        }
        return self::$connections[$connectionName];
    }

    /**
     * Disconnect all adapters
     */
    static public function disconnectAll() {
        foreach (self::$connections as $adapter) {
            $adapter->disconnect();
        }
    }


}