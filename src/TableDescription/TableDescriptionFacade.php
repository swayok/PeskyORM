<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\TableDescription\TableDescribers\TableDescriberInterface;
use PeskyORM\Utils\ArgumentValidators;
use PeskyORM\Utils\ServiceContainer;

abstract class TableDescriptionFacade
{
    public static function registerDescriberClass(
        string $dbAdapterName,
        string $describerClass
    ): void {
        ArgumentValidators::assertClassImplementsInterface(
            '$describerClass',
            $describerClass,
            TableDescriberInterface::class
        );
        ServiceContainer::getInstance()->bind(
            ServiceContainer::TABLE_DESCRIBER_CLASS . $dbAdapterName,
            function () use ($describerClass) {
                return $describerClass;
            },
            false
        );
    }

    public static function getDescriber(DbAdapterInterface $dbAdapter): TableDescriberInterface
    {
        return ServiceContainer::getInstance()->make(
            TableDescriberInterface::class,
            [$dbAdapter]
        );
    }

    public static function describeTable(
        DbAdapterInterface $dbAdapter,
        string $tableName,
        ?string $schemaName = null
    ): TableDescriptionInterface {
        return static::getDescriber($dbAdapter)
            ->getTableDescription($tableName, $schemaName);
    }
}