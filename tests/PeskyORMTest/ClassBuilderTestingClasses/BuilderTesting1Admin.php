<?php
declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\ClassBuilderTestingClasses;

use Carbon\CarbonImmutable;
use PeskyORM\ORM\Record\Record;

/**
 * @property null|int           $id
 * @property string             $login
 * @property string             $password
 * @property null|int           $parent_id
 * @property string             $created_at
 * @property string             $created_at_as_date
 * @property string             $created_at_as_time
 * @property int                $created_at_as_unix_ts
 * @property CarbonImmutable    $created_at_as_carbon
 * @property string             $updated_at
 * @property string             $updated_at_as_date
 * @property string             $updated_at_as_time
 * @property int                $updated_at_as_unix_ts
 * @property CarbonImmutable    $updated_at_as_carbon
 * @property null|string        $remember_token
 * @property bool               $is_superadmin
 * @property null|string        $language
 * @property null|string        $ip
 * @property string             $role
 * @property bool               $is_active
 * @property string             $name
 * @property null|string        $email
 * @property string             $timezone
 * @property null|string        $not_changeable_column
 * @property string             $big_data
 *
 * @method $this setId              (mixed $value, bool $isFromDb = false)
 * @method $this setLogin           (mixed $value, bool $isFromDb = false)
 * @method $this setPassword        (mixed $value, bool $isFromDb = false)
 * @method $this setParentId        (mixed $value, bool $isFromDb = false)
 * @method $this setCreatedAt       (mixed $value, bool $isFromDb = false)
 * @method $this setUpdatedAt       (mixed $value, bool $isFromDb = false)
 * @method $this setRememberToken   (mixed $value, bool $isFromDb = false)
 * @method $this setIsSuperadmin    (mixed $value, bool $isFromDb = false)
 * @method $this setLanguage        (mixed $value, bool $isFromDb = false)
 * @method $this setIp              (mixed $value, bool $isFromDb = false)
 * @method $this setRole            (mixed $value, bool $isFromDb = false)
 * @method $this setIsActive        (mixed $value, bool $isFromDb = false)
 * @method $this setName            (mixed $value, bool $isFromDb = false)
 * @method $this setEmail           (mixed $value, bool $isFromDb = false)
 * @method $this setTimezone        (mixed $value, bool $isFromDb = false)
 * @method $this setNotChangeableColumn (mixed $value, bool $isFromDb = false)
 * @method $this setBigData         (mixed $value, bool $isFromDb = false)
 */
class BuilderTesting1Admin extends Record
{
    public function __construct()
    {
        parent::__construct(BuilderTesting1AdminsTable::getInstance());
    }
}