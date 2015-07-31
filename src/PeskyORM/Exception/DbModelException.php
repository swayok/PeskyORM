<?php

namespace PeskyORM\Exception;

use PeskyORM\DbModel;

class DbModelException extends DbException {

    protected $dbModel;

    public function __construct(DbModel $dbModel, $message, $errorCode = null) {
        $this->dbModel = $dbModel;
        try {
            $ds = $dbModel->hasConnectionToDataSource() ? $dbModel->getDataSource() : null;
        } catch (DbModelException $exc) {
            $ds = null;
        }
        parent::__construct($ds, $message, $errorCode);
    }

    /**
     * @return DbModel
     */
    public function getDbModel() {
        return $this->dbModel;
    }



}