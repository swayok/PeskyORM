<?php

namespace PeskyORM\DbColumnConfig;

use PeskyORM\DbColumnConfig;
use PeskyORM\Exception\DbColumnConfigException;

class PasswordColumnConfig extends DbColumnConfig {

    /**
     * @var callable|null
     */
    protected $hashFunction = null;

    /**
     * @param string $name
     * @param null $notUsed
     * @return $this
     */
    static public function create($name = null, $notUsed = null) {
        return new PasswordColumnConfig($name);
    }

    public function __construct($name = null, $notUsed = null) {
        parent::__construct($name, self::TYPE_PASSWORD);
    }

    /**
     * @param callable $hashFunction - something like: function ($password) { return sha1($password); }
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setHashFunction($hashFunction) {
        if (!is_callable($hashFunction)) {
            throw new DbColumnConfigException($this, '$hashFunction argument must be callable');
        }
        $this->hashFunction = $hashFunction;
        return $this;
    }

    /**
     * @return callable
     */
    public function getHashFunction() {
        return $this->hashFunction;
    }

    /**
     * @return bool
     */
    public function hasHashFunction() {
        return $this->hashFunction !== null;
    }

}