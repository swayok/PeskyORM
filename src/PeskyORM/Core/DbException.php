<?php

namespace PeskyORM\Core;

class DbException extends \PDOException {

    const CODE_INSERT_FAILED = 13401;

    const CODE_ADAPTER_IMPLEMENTATION_PROBLEM = 13501;

    

}