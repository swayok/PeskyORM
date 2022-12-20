<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\ORM\RecordsCollection\RecordsArray;
use PeskyORM\ORM\RecordsCollection\SelectedRecordsCollectionInterface;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

/**
 * Basic funcitonality tested via Table and Record tests.
 * Here we test some specific functionality.
 */
class RecordsCollectionTest extends BaseTestCase
{
    public function testHasManyInjectionIntoSelectedRecordsArray(): void
    {
        $insertedData = $this->fillAdminsTable();
        $expectedRecords = [];
        $relatedRecordsGrouped = [];
        foreach ($insertedData as $admin) {
            $admin['created_at'] .= '+00';
            $admin['updated_at'] .= '+00';
            unset($admin['big_data']);
            if ($admin['parent_id'] === null) {
                $expectedRecords[$admin['id']] = $admin;
            } else {
                $relatedRecordsGrouped[$admin['parent_id']][$admin['id']] = $admin;
            }
        }
        static::assertNotEmpty($expectedRecords);
        static::assertNotEmpty($relatedRecordsGrouped);
        ksort($expectedRecords, SORT_NUMERIC);
        $expectedRecords = array_values($expectedRecords);

        $recordsArray = TestingAdminsTable::select('*', [
            'parent_id' => null,
            'ORDER' => ['id' => "ASC"]
        ]);
        static::assertInstanceOf(SelectedRecordsCollectionInterface::class, $recordsArray);
        static::assertEquals($expectedRecords, $recordsArray->toArrays());

        // inject into already selected records
        $recordsArray->injectHasManyRelationData(
            'Children',
            ['*'],
            ['ORDER' => ['id' => 'ASC']]
        );
        $expectedRecordsNested = [];
        foreach ($expectedRecords as $record) {
            $children = $relatedRecordsGrouped[$record['id']] ?? [];
            ksort($children, SORT_NUMERIC);
            $record['Children'] = array_values($children);
            $expectedRecordsNested[] = $record;
        }
        static::assertEquals($expectedRecordsNested, $recordsArray->toArrays());

        // delayed injection
        $lastQuery = TestingAdminsTable::getLastQuery(false);
        $recordsArray = TestingAdminsTable::select('*', [
            'parent_id' => null,
            'ORDER' => ['id' => "ASC"]
        ]);
        static::assertEquals($lastQuery, TestingAdminsTable::getLastQuery(false));
        // inject into not yet selected records
        $recordsArray->injectHasManyRelationData(
            'Children',
            ['*'],
            ['ORDER' => ['id' => 'ASC']]
        );
        static::assertEquals($lastQuery, TestingAdminsTable::getLastQuery(false));
        // no queries executed yet
        static::assertEquals($expectedRecordsNested, $recordsArray->toArrays());
    }

    public function testHasManyInjectionIntoRecordsArray(): void
    {
        $insertedData = $this->fillAdminsTable();
        $lastQuery = TestingAdminsTable::getLastQuery(false);
        $expectedRecords = [];
        $relatedRecordsGrouped = [];
        foreach ($insertedData as $admin) {
            $admin['created_at'] .= '+00';
            $admin['updated_at'] .= '+00';
            unset($admin['big_data']);
            if ($admin['parent_id'] === null) {
                $expectedRecords[$admin['id']] = $admin;
            } else {
                $relatedRecordsGrouped[$admin['parent_id']][$admin['id']] = $admin;
            }
        }
        static::assertNotEmpty($expectedRecords);
        static::assertNotEmpty($relatedRecordsGrouped);
        ksort($expectedRecords, SORT_NUMERIC);
        $expectedRecords = array_values($expectedRecords);

        $recordsArray = new RecordsArray(
            TestingAdminsTable::getInstance(),
            $expectedRecords,
            true
        );
        static::assertEquals($expectedRecords, $recordsArray->toArrays());
        static::assertEquals($lastQuery, TestingAdminsTable::getLastQuery(false));

        $recordsArray->injectHasManyRelationData(
            'Children',
            ['*'],
            ['ORDER' => ['id' => 'ASC']]
        );
        $expectedRecordsNested = [];
        foreach ($expectedRecords as $record) {
            $children = $relatedRecordsGrouped[$record['id']] ?? [];
            ksort($children, SORT_NUMERIC);
            $record['Children'] = array_values($children);
            $expectedRecordsNested[] = $record;
        }
        static::assertNotEquals($lastQuery, TestingAdminsTable::getLastQuery(false));
        static::assertEquals($expectedRecordsNested, $recordsArray->toArrays());
    }
}