<?php

namespace PeskyORM\ORM;

interface ValueToObjectConverterInterface
{
    
    /**
     * @return static
     */
    public static function createObjectFromArray(array $data);
    
}