<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Data;

trait TestDataForSettingsTable
{
    
    protected function getTestDataForSettingsTableInsert(): array
    {
        return [
            [
                'id' => 1,
                'key' => 'key1',
                'value' => json_encode([], JSON_UNESCAPED_UNICODE)
            ],
            [
                'id' => 2,
                'key' => 'key2',
                'value' => json_encode(['test1', 'test2', 'test3'], JSON_UNESCAPED_UNICODE)
            ],
            [
                'id' => 3,
                'key' => 'key3',
                'value' => json_encode([
                    'test4' => ['sub1' => 'val1'],
                    'test5' => ['sub2' => 'val2'],
                    'test6' => ['sub3' => 'val3']
                ], JSON_UNESCAPED_UNICODE)
            ],
        ];
    }
}