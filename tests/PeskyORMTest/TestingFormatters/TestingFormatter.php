<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingFormatters;

use Carbon\CarbonImmutable;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\RecordValue;
use PeskyORM\ORM\TableInterface;

/**
 * @property int    $id
 *
 * @property string $created_at
 * @property string $created_at_as_date
 * @property string $created_at_as_time
 * @property int    $created_at_as_unix_ts
 * @property CarbonImmutable $created_at_as_carbon
 *
 * @property string $created_at_unix
 * @property string $created_at_unix_as_date
 * @property string $created_at_unix_as_time
 * @property string $created_at_unix_as_date_time
 * @property CarbonImmutable $created_at_unix_as_carbon
 *
 * @property string $creation_date
 * @property int    $creation_date_as_unix_ts
 * @property CarbonImmutable $creation_date_as_carbon
 *
 * @property string $creation_time
 * @property int    $creation_time_as_unix_ts
 *
 * @property string $json_data1
 * @property array  $json_data1_as_array
 * @property \stdClass $json_data1_as_object
 *
 * @property string $json_data2
 * @property array  $json_data2_as_array
 * @property TestingFormatterJsonObject $json_data2_as_object
 *
 * @method $this setId($value, $isFromDb = false)
 * @method $this setCreatedAt($value, $isFromDb = false)
 * @method $this setCreatedAtUnix($value, $isFromDb = false)
 * @method $this setCreationDate($value, $isFromDb = false)
 * @method $this setCreationTime($value, $isFromDb = false)
 * @method $this setJsonData1($value, $isFromDb = false)
 * @method $this setJsonData2($value, $isFromDb = false)
 */
class TestingFormatter extends Record
{
    
    public static function getTable(): TableInterface
    {
        return TestingFormattersTable::getInstance();
    }

    public function getValueContainer($colNameOrConfig): RecordValue
    {
        return parent::getValueContainer($colNameOrConfig);
    }
}