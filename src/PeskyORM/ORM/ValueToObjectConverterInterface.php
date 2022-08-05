<?php

namespace PeskyORM\ORM;

interface ValueToObjectConverterInterface
{
    /**
     * @return static
     */
    static public function createObjectFromArray(array $data);
    
    /**
     * Convert other $object to object of this class
     * @param object $object
     * @return static
     */
    static public function createObjectFromObject($object);
}