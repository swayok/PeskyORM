<?php

namespace PeskyORM\ORM;

use PeskyORM\ORM\Traits\ConvertsArrayToObject;

abstract class ValueToObjectConverter implements ValueToObjectConverterInterface
{
    
    use ConvertsArrayToObject;
    
    public $other = [];
    
    public function handleUnknownArrayKeys(array $unknownProperties, array $data)
    {
        $this->other = $unknownProperties;
    }
    
    public static function createObjectFromObject($object) {
        throw new \BadMethodCallException('Cannot convert object of class ' . get_class($object). ' to object of class ' . static::class);
    }
}