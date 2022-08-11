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
    
}