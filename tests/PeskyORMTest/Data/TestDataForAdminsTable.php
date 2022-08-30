<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\Data;

use PeskyORM\Adapter\Mysql;
use PeskyORM\Adapter\Postgres;

trait TestDataForAdminsTable
{
    
    protected function getTestDataForAdminsTableInsert(): array
    {
        return [
            [
                'id' => 1,
                'login' => '2AE351AF-131D-6654-9DB2-79B8F273986C',
                'password' => password_hash('KIS37QEG4HT', PASSWORD_DEFAULT),
                'parent_id' => null,
                'created_at' => '2015-05-14 02:12:05',
                'updated_at' => '2015-06-10 19:30:24',
                'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
                'is_superadmin' => true,
                'language' => 'en',
                'ip' => '192.168.0.1',
                'role' => 'admin',
                'is_active' => 1,
                'name' => 'Lionel Freeman',
                'email' => 'diam.at.pretium@idmollisnec.co.uk',
                'timezone' => 'Europe/Moscow',
                'big_data' => 'biiiiiiig data',
            ],
            [
                'id' => 2,
                'login' => 'ADCE237A-9E48-BECD-1F01-1CACA964CF0F',
                'password' => password_hash('NKJ63NMV6NY', PASSWORD_DEFAULT),
                'parent_id' => 1,
                'created_at' => '2015-05-14 06:54:01',
                'updated_at' => '2015-05-19 23:48:17',
                'remember_token' => '0A2E7DA9-6072-34E2-38E8-2675C73F3419',
                'is_superadmin' => true,
                'language' => 'en',
                'ip' => '192.168.0.1',
                'role' => 'admin',
                'is_active' => false,
                'name' => 'Jasper Waller',
                'email' => 'elit@eratvelpede.org',
                'timezone' => 'Europe/Moscow',
                'big_data' => 'biiiiiiig data',
            ],
        ];
    }
    
    public function convertTestDataForAdminsTableAssert(array $data, bool $convertIdToString = true): array
    {
        $adapter = $this->getValidAdapter();
        if ($adapter instanceof Postgres) {
            foreach ($data as &$item) {
                $item['id'] = $convertIdToString ? (string)$item['id'] : (int)$item['id'];
                $item['is_superadmin'] = (bool)$item['is_superadmin'];
                $item['is_active'] = (bool)$item['is_active'];
                $item['not_changeable_column'] = 'not changable';
                $item['created_at'] .= '+00';
                $item['updated_at'] .= '+00';
            }
        } elseif ($adapter instanceof Mysql) {
            foreach ($data as &$item) {
                $item['id'] = (string)$item['id'];
                $item['is_superadmin'] = $item['is_superadmin'] ? '1' : '0';
                $item['is_active'] = $item['is_active'] ? '1' : '0';
                $item['not_changeable_column'] = 'not changable';
            }
        }
        return $data;
    }
}