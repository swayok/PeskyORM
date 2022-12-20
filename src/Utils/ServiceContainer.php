<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Adapter\Mysql;
use PeskyORM\Config\Connection\DbConnectionConfigInterface;
use PeskyORM\Config\Connection\DbConnectionsFacade;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Exception\ServiceContainerException;
use PeskyORM\Exception\ServiceNotFoundException;
use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\RecordsCollection\RecordsArray;
use PeskyORM\ORM\RecordsCollection\RecordsCollectionInterface;
use PeskyORM\ORM\RecordsCollection\SelectedRecordsArray;
use PeskyORM\ORM\RecordsCollection\SelectedRecordsCollectionInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesEn;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumnFactory;
use PeskyORM\ORM\TableStructure\TableColumnFactoryInterface;
use PeskyORM\Select\OrmSelect;
use PeskyORM\Select\OrmSelectQueryBuilderInterface;
use PeskyORM\Select\Select;
use PeskyORM\Select\SelectQueryBuilderInterface;
use PeskyORM\TableDescription\TableDescribers\MysqlTableDescriber;
use PeskyORM\TableDescription\TableDescribers\PostgresTableDescriber;
use PeskyORM\TableDescription\TableDescribers\TableDescriberInterface;
use PeskyORM\TableDescription\TableDescriptionFacade;
use PeskyORM\Tests\PeskyORMTest\Adapter\PostgresTesting;

class ServiceContainer implements ServiceContainerInterface
{
    public const DB_ADAPTER = 'peskyorm.db_adapter.';
    public const DB_CONNECTION_CONFIG = 'peskyorm.db_config.';
    public const DB_CONNECTION = 'peskyorm.db_connection.';
    public const TABLE_DESCRIBER = 'peskyorm.table_describer.';

    public const MYSQL = 'mysql';
    public const POSTGRES = 'pgsql';

    private array $instances = [];
    private array $bindings = [];
    private array $aliases = [];

    private static ?ServiceContainerInterface $instance = null;

    public static function getInstance(): ServiceContainerInterface
    {
        if (!self::$instance) {
            self::$instance = new static();
            static::fillContainer(self::$instance);
        }
        return self::$instance;
    }

    /**
     * Use this method to replace service container by your own.
     * You can make an adapter/proxy class that implements ServiceContainerInterface
     * to use any service container from any framework.
     * Use $container = null to use this class instead of replaced one or reset container state
     */
    public static function replaceContainer(?ServiceContainerInterface $container): void
    {
        self::$instance = $container;
        if ($container) {
            static::fillContainer($container);
        }
    }

    /**
     * Fill service container with default ORM bindings
     */
    public static function fillContainer(ServiceContainerInterface $container): void
    {
        // MySQL
        DbConnectionsFacade::registerAdapter(static::MYSQL, Mysql::class);
        DbConnectionsFacade::registerConnectionConfigClass(static::MYSQL, MysqlConfig::class);
        TableDescriptionFacade::registerDescriber(static::MYSQL, MysqlTableDescriber::class);
        // PostgreSQL
        DbConnectionsFacade::registerAdapter(static::POSTGRES, PostgresTesting::class);
        DbConnectionsFacade::registerConnectionConfigClass(static::POSTGRES, PostgresConfig::class);
        TableDescriptionFacade::registerDescriber(static::POSTGRES, PostgresTableDescriber::class);
        // Common
        $container
            // DB connection config creator
            ->bind(
                DbConnectionConfigInterface::class,
                static function (
                    string $dbEngineName,
                    array $configs,
                    ?string $name = null
                ) use ($container): DbConnectionConfigInterface {
                    /** @var DbConnectionConfigInterface $configClass */
                    $configClass = $container->make(static::DB_CONNECTION_CONFIG . $dbEngineName);
                    return $configClass::fromArray($configs, $name);
                },
                false
            )
            // DB connection getter
            ->bind(
                DbAdapterInterface::class,
                static function (
                    string $connectionName = 'default'
                ) use ($container): DbAdapterInterface {
                    return $container->make(static::DB_CONNECTION . $connectionName);
                },
                false
            )
            // DB table describer creator
            ->bind(
                TableDescriberInterface::class,
                static function (
                    DbAdapterInterface $adapter
                ) use ($container): TableDescriberInterface {
                    return $container->make(
                        static::TABLE_DESCRIBER . $adapter->getName(),
                        [$adapter]
                    );
                },
                false
            )
            // Table columns factory
            ->bind(TableColumnFactoryInterface::class, TableColumnFactory::class, true)
            // Selects
            ->bind(SelectQueryBuilderInterface::class, Select::class, false)
            ->bind(OrmSelectQueryBuilderInterface::class, OrmSelect::class, false)
            // Records arrays
            ->bind(RecordsCollectionInterface::class, RecordsArray::class, false)
            ->bind(SelectedRecordsCollectionInterface::class, SelectedRecordsArray::class, false)
            // Record value conatiner
            ->bind(RecordValueContainerInterface::class, RecordValue::class, false)
            // Column validation messages
            ->bind(
                ColumnValueValidationMessagesInterface::class,
                ColumnValueValidationMessagesEn::class,
                true
            )
            ;
    }

    private function __construct()
    {
    }

    /**
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     * @throws ServiceNotFoundException
     * @see ServiceContainerInterface::make()
     */
    public function get(string $abstract): mixed
    {
        if (!$this->has($abstract)) {
            throw new ServiceNotFoundException(
                "Cannot find definition for [{$abstract}]."
            );
        }
        return $this->make($abstract);
    }

    /**
     * Check if container knows about an abstract
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function has(string $abstract): bool
    {
        return (
            isset($this->instances[$abstract])
            || isset($this->bindings[$abstract])
            || isset($this->aliases[$abstract])
        );
    }

    public function instance(
        string $abstract,
        string|object|null $instance = null
    ): static {
        if (!$instance || is_string($instance) || $instance instanceof \Closure) {
            $this->bind($abstract, $instance, true);
        } else {
            $this->instances[$abstract] = $instance;
        }
        return $this;
    }

    public function bind(
        string $abstract,
        \Closure|string|null $concrete = null,
        bool $singleton = false
    ): static {
        unset($this->instances[$abstract]);
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'singleton' => $singleton,
        ];
        return $this;
    }

    public function unbind(string $abstract): static
    {
        unset(
            $this->bindings[$abstract],
            $this->instances[$abstract],
            $this->aliases[$abstract]
        );
        return $this;
    }

    public function alias(string $abstract, string $alias): static
    {
        if ($abstract !== $alias) {
            $this->aliases[$alias] = $abstract;
        }
        return $this;
    }

    public function make(
        string $abstract,
        array $parameters = []
    ): mixed {
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }
        // It is a singleton, so we'll just return existing instance.
        // Paramenters are ignored. This is simple service container.
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            $this->bind($abstract);
        }
        $concrete = $this->bindings[$abstract]['concrete'];
        $isSingleton = $this->bindings[$abstract]['singleton'];

        // Class was already resolved earlier -> reuse reflector
        if ($concrete instanceof \ReflectionClass) {
            // Note: it cannot be a singleton
            return $concrete->newInstanceArgs($parameters);
        }

        if ($concrete instanceof \Closure) {
            $object = call_user_func_array($concrete, $parameters);
            if ($isSingleton) {
                $this->instances[$abstract] = $object;
            }
            return $object;
        }

        // Concrete is a class
        try {
            $reflector = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new ServiceContainerException(
                "Concrete class [{$concrete}] for abstract [{$abstract}] does not exist.",
                previous: $e
            );
        }

        if (!$reflector->isInstantiable()) {
            throw new ServiceContainerException(
                "Concrete class [{$concrete}] for abstract [{$abstract}] is not instantiable.",
            );
        }

        $object = $reflector->newInstanceArgs($parameters);
        if ($isSingleton) {
            $this->instances[$abstract] = $object;
        } else {
            // Save reflector instance for reuse
            $this->bindings[$abstract]['concrete'] = $reflector;
        }
        return $object;
    }
}