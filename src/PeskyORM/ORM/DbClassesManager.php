<?php

namespace PeskyORM\ORM;

abstract class DbClassesManager implements DbClassesManagerInterface {

    /**
     * @var DbClassesManager
     */
    static private $instance = null;

    /**
     * @return $this
     * @throws \BadMethodCallException
     */
    final static public function getInstance() {
        if (DbClassesManager::$instance === null) {
            if (get_called_class() === __CLASS__) {
                throw new \BadMethodCallException(
                    'Class ' . __CLASS__ . ' cannot be used directly. You need to extend it and call getInstance() '
                    . ' method from child class. Note: only 1 child class can be used at once.'
                );
            }
            DbClassesManager::$instance = new static();
        }
        return DbClassesManager::$instance;
    }

    /**
     * Final constructor to be sure there is no other instances
     */
    final protected function __construct() {

    }

    /**
     * Get instance of a manager
     * @return $this
     * @throws \BadMethodCallException
     */
    final static public function i() {
        return static::getInstance();
    }

}