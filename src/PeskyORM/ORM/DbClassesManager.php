<?php

namespace PeskyORM\ORM;

abstract class DbClassesManager implements DbClassesManagerInterface {

    /**
     * @var DbClassesManager
     */
    static private $instance = null;

    /**
     * Craeate class instance. Must be called from a child class
     * @throws \BadMethodCallException
     */
    final static public function init() {
        if (DbClassesManager::$instance !== null) {
            throw new \BadMethodCallException('Class already initiated');
        }
        if (get_called_class() === __CLASS__) {
            throw new \BadMethodCallException(
                'Class ' . __CLASS__ . ' cannot be used directly. You need to extend it and call init() '
                . ' method from child class. Note: only 1 child class can be used at once.'
            );
        }
        DbClassesManager::$instance = new static();
    }

    /**
     * Get instance of a manager
     * @return $this
     * @throws \BadMethodCallException
     */
    final static public function getInstance() {
        if (DbClassesManager::$instance === null) {
            throw new \BadMethodCallException('Class must be initiated first');
        }
        return DbClassesManager::$instance;
    }

    /**
     * Get instance of a manager
     * @return $this
     * @throws \BadMethodCallException
     */
    final static public function i() {
        return static::getInstance();
    }

    final protected function __construct() {

    }

}