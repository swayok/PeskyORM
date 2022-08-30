<?php

namespace PeskyORM\ORM\Traits;

trait ConvertsArrayToObject
{
    
    public static function createObjectFromArray(array $data): static
    {
        $obj = new static();
        $unknownProperties = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && property_exists($obj, $key)) {
                $obj->$key = $value;
            } else {
                $unknownProperties[$key] = $value;
            }
        }
        if (!empty($unknownProperties)) {
            $obj->handleUnknownArrayKeys($unknownProperties, $data);
        }
        return $obj;
    }
    
    /**
     * @param array $data - all data
     * @param array $unknownProperties - key: property name => value: property value
     */
    public function handleUnknownArrayKeys(array $unknownProperties, array $data): void
    {
        // implement your handler if needed
    }
}