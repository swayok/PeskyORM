<?php

namespace PeskyORM\Exception;

class DbException extends \PDOException
{
    
    public const CODE_ADAPTER_IMPLEMENTATION_PROBLEM = 13001;
    public const CODE_DB_DOES_NOT_SUPPORT_FEATURE = 13002;
    
    public const CODE_INSERT_FAILED = 13401;
    public const CODE_RETURNING_FAILED = 13402;
    
    public const CODE_TRANSACTION_BEGIN_FAIL = 13402;
    public const CODE_TRANSACTION_COMMIT_FAIL = 13403;
    public const CODE_TRANSACTION_ROLLBACK_FAIL = 13404;
    
}