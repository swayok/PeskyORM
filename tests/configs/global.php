<?php

return [

    'pgsql' => [
        'valid' => [
            'name' => 'pesky_orm_test_db',
            'host' => '192.168.0.251',
            'port' => '5432',
            'user' => 'pesky_orm_test',
            'password' => '1111111'
        ],
        'invalid' => [
            'name' => 'not_existing_db',
            'host' => '127.0.0.1',
            'port' => null,
            'user' => '',
            'password' => ''
        ]
    ],

];