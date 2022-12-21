<?php

declare(strict_types=1);

namespace PeskyORM\Config\Connection;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Utils\ArgumentValidators;

abstract class DbConnectionConfigAbstract implements DbConnectionConfigInterface
{
    protected string $dbName;
    protected string $dbUser;
    protected string $dbPassword;
    protected ?string $configName = null;
    protected string $dbHost;
    protected string $dbPort;
    protected string $charset;
    protected array $options = [];
    protected ?string $timezone = null;
    protected array $onConnectCallbacks = [];

    public function getName(): string
    {
        return $this->configName ?: $this->dbName;
    }

    public function setName(string $name): static
    {
        $this->configName = $name;
        return $this;
    }

    public function getUserName(): string
    {
        return $this->dbUser;
    }

    public function getUserPassword(): string
    {
        return $this->dbPassword;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setDbHost(string $dbHost): static
    {
        ArgumentValidators::assertNotEmpty('$dbHost', $dbHost);
        $this->dbHost = $dbHost;
        return $this;
    }

    public function getDbHost(): string
    {
        return $this->dbHost;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setDbPort(int|string $dbPort): static
    {
        ArgumentValidators::assertPositiveInteger('$dbPort', $dbPort, false);
        $this->dbPort = trim((string)$dbPort);
        return $this;
    }

    public function getDbPort(): string
    {
        return $this->dbPort;
    }

    /**
     * Set options for PDO connection (key-value)
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * GET options for PDO connection
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setCharset(string $charset): static
    {
        ArgumentValidators::assertNotEmpty('$charset', $charset);
        $this->charset = $charset;
        $this->addOnConnectCallback(
            function (DbAdapterInterface $connection) {
                $connection->setCharacterSet($this->charset);
            },
            'charset'
        );
        return $this;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;
        if ($this->timezone) {
            $this->addOnConnectCallback(
                function (DbAdapterInterface $connection) {
                    $connection->setTimezone($this->timezone);
                },
                'timezone'
            );
        } else {
            $this->removeOnConnectCallback('timezone');
        }
        return $this;
    }

    public function onConnect(DbAdapterInterface $connection): void
    {
        foreach ($this->onConnectCallbacks as $callback) {
            $callback($connection);
        }
    }

    public function addOnConnectCallback(
        \Closure $onConnect,
        string $uniqueName = null
    ): static {
        if ($uniqueName) {
            $this->onConnectCallbacks[$uniqueName] = $onConnect;
        } else {
            $this->onConnectCallbacks[] = $onConnect;
        }
        return $this;
    }

    protected function removeOnConnectCallback(string $uniqueName): void
    {
        unset($this->onConnectCallbacks[$uniqueName]);
    }
}