<?php

namespace PeskyORM\Exception;

use PeskyORM\DbModel;

class DbModelException extends DbException {

    protected $dbModel;

    public function __construct(DbModel $dbModel, $message) {
        $this->dbModel = $dbModel;
        parent::__construct($dbModel->getDataSource(), $message);
    }

    /**
     * @return DbModel
     */
    public function getDbModel() {
        return $this->dbModel;
    }



}