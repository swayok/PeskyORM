<?php

declare(strict_types=1);

namespace PeskyORM\Config\Connection;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Profiling\TraceablePDO;
use PeskyORM\Utils\ArgumentValidators;
use PeskyORM\Utils\ServiceContainer;
use PeskyORM\Utils\ServiceContainerInterface;

abstract class DbConnectionsFacade
{
    protected static array $registeredConnetionNames = [];

    /**
     * Register DB adapter
     *
     * @param string $adapterName
     * @param string $adapterClass - class must implement DbAdapterInterface
     *
     * @throws \InvalidArgumentException
     * @see DbAdapterInterface
     */
    public static function registerAdapter(
        string $adapterName,
        string $adapterClass
    ): void {
        ArgumentValidators::assertClassImplementsInterface(
            '$adapterClass',
            $adapterClass,
            DbAdapterInterface::class
        );
        $reflector = new \ReflectionClass($adapterClass);
        ServiceContainer::getInstance()->bind(
            ServiceContainer::DB_ADAPTER . $adapterName,
            function (
                ServiceContainerInterface $container,
                array $args = []
            ) use ($reflector) {
                return $reflector->newInstanceArgs($args);
            },
            false
        );
    }

    /**
     * Register connection config class for DB adapter
     *
     * @param string $adapterName
     * @param string $connectionConfigClass - class must implement DbConnectionConfigInterface
     *
     * @throws \InvalidArgumentException
     * @see DbConnectionConfigInterface
     */
    public static function registerConnectionConfigClass(
        string $adapterName,
        string $connectionConfigClass
    ): void {
        ArgumentValidators::assertClassImplementsInterface(
            '$connectionConfigClass',
            $connectionConfigClass,
            DbConnectionConfigInterface::class
        );
        ServiceContainer::getInstance()->bind(
            ServiceContainer::DB_CONNECTION_CONFIG_CLASS . $adapterName,
            function () use ($connectionConfigClass) {
                return $connectionConfigClass;
            },
            false
        );
    }

    /**
     * Register new connection for adapter.
     * $connectionConfig closure signature: function(): DbConnectionConfigInterface
     * @see ServiceContainer::MYSQL for default adapter name for MySQL connections
     * @see ServiceContainer::POSTGRES for default adapter name for PostgreSQL connections
     * @see self::registerAdapter() for custom adapter name
     */
    public static function registerConnection(
        string $connectionName,
        string $adapterName,
        DbConnectionConfigInterface|\Closure $connectionConfig,
    ): void {
        static::$registeredConnetionNames[] = $connectionName;
        ServiceContainer::getInstance()->bind(
            ServiceContainer::DB_CONNECTION . $connectionName,
            static function (ServiceContainerInterface $container) use ($connectionConfig, $adapterName) {
                if ($connectionConfig instanceof \Closure) {
                    $connectionConfig = $connectionConfig();
                }
                return $container->make(
                    ServiceContainer::DB_ADAPTER . $adapterName,
                    [
                        $connectionConfig,
                        $adapterName,
                    ]
                );
            },
            true
        );
    }

    /**
     * Get names of registered connections
     */
    public static function getRegisteredConnectionsNames(): array
    {
        return array_unique(static::$registeredConnetionNames);
    }

    /**
     * Required $connectionInfo keys:
     *      - 'driver' or 'adapter': 'mysql', 'pgsql', 'adapter name', ...
     * Optional $connectionInfo keys (depends on driver):
     *      - 'database',
     *      - 'username',
     *      - 'password',
     *      - 'charset'
     *      - 'host'
     *      - 'port'
     *      - 'socket'
     *      - 'options' (array)
     * @throws \InvalidArgumentException when $connectionInfo has no 'driver' or 'adapter' keys
     * @see MysqlConfig::fromArray()
     * @see PostgresConfig::fromArray()
     */
    public static function createConnectionConfigFromArray(
        string $connectionName,
        array $connectionInfo,
    ): DbConnectionConfigInterface {
        if (empty($connectionInfo['driver']) && empty($connectionInfo['adapter'])) {
            throw new \InvalidArgumentException(
                '$connectionInfo must contain a value for key \'driver\' or \'adapter\''
            );
        }
        $adapterName = empty($connectionInfo['adapter'])
            ? $connectionInfo['driver']
            : $connectionInfo['adapter'];

        return ServiceContainer::getInstance()->make(
            ServiceContainer::DB_CONNECTION_CONFIG_FACTORY,
            [
                $adapterName,
                $connectionInfo,
                $connectionName,
            ]
        );
    }

    /**
     * Add alternative name for existing connection
     *
     * @param string $connectionName
     * @param string $alternativeName
     */
    public static function registerAliasForConnection(
        string $connectionName,
        string $alternativeName,
    ): void {
        ServiceContainer::getInstance()->alias(
            ServiceContainer::DB_CONNECTION . $connectionName,
            ServiceContainer::DB_CONNECTION . $alternativeName,
        );
    }

    public static function getConnection(string $connectionName): DbAdapterInterface
    {
        return ServiceContainer::getInstance()
            ->make(ServiceContainer::DB_CONNECTION . $connectionName);
    }

    public static function startProfilingForConnection(DbAdapterInterface $connection): void
    {
        $connection->setConnectionWrapper(function (DbAdapterInterface $adapter, \PDO $pdo) {
            $name = $adapter->getConnectionConfig()->getName()
                . ' (DB: ' . $adapter->getConnectionConfig()->getDbName() . ')';
            return new TraceablePDO($pdo, $name);
        });
    }
}
