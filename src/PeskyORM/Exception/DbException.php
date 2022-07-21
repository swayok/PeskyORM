<?php

namespace PeskyORM\Exception;

class DbException extends \PDOException
{
    
    const CODE_ADAPTER_IMPLEMENTATION_PROBLEM = 13001;
    const CODE_DB_DOES_NOT_SUPPORT_FEATURE = 13002;
    
    const CODE_INSERT_FAILED = 13401;
    const CODE_RETURNING_FAILED = 13402;
    
    const CODE_TRANSACTION_BEGIN_FAIL = 13402;
    const CODE_TRANSACTION_COMMIT_FAIL = 13403;
    const CODE_TRANSACTION_ROLLBACK_FAIL = 13404;
    
}