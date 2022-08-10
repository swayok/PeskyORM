<?php

namespace PeskyORM\ORM;

interface ValueToObjectConverterInterface
{
    /**
     * @return static
     */
    public static function createObjectFromArray(array $data);
    
    /**
     * Convert other $object to object of this class
     * @param object $object
     * @return static
     */
    public static function createObjectFromObject($object);
}