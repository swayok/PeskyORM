<?php

namespace PeskyORM\ORM;

abstract class DbClassesManager implements DbClassesManagerInterface {

    /**
     * @var DbClassesManager
     */
    static protected $instance = null;

    static public function getInstance() {
        if (self::$instance === null) {
            if (get_called_class() === __CLASS__) {
                throw new \BadMethodCallException(
                    'Class ' . __CLASS__ . ' cannot be used directly. You need to extend it and call getInstance() '
                    . ' method from child class. Note: only 1 child class can be used at once.'
                );
            }
            self::$instance = new static();
        }
        return self::$instance;
    }

}