<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

class KeyValuePair
{
    
    /**
     * @var bool|float|int|string
     */
    protected $key;
    /**
     * @return mixed
     */
    protected $value;
    
    /**
     * @param int|float|string|bool $key
     * @param mixed $value
     * @return static
     */
    public static function create($key, $value)
    {
        return new static($key, $value);
    }
    
    /**
     * @param int|float|string|bool $key
     * @param mixed $value
     */
    public function __construct($key, $value)
    {
        if (!is_scalar($key)) {
            throw new \InvalidArgumentException(
                '$key argument must contain a scalar value (int, float, string, bool). '
                . var_export($key, true) . ' received'
            );
        }
        $this->key = $key;
        $this->value = $value;
    }
    
    /**
     * @return bool|float|int|string
     */
    public function getKey()
    {
        return $this->key;
    }
    
    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
    
}