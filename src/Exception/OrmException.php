<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

class OrmException extends \Exception
{
    public const CODE_INVALID_TABLE_STRUCTURE_CONFIG = 50001;
    public const CODE_INVALID_TABLE_COLUMN_CONFIG = 50002;
    public const CODE_INVALID_TABLE_RELATION_CONFIG = 50003;
    public const CODE_RECORD_NOT_FOUND_EXCEPTION = 40402;
    public const CODE_INVALID_DATA = 40001;
}