<?php

namespace PeskyORM\ORM;

interface ValueToObjectConverterInterface
{
    
    public static function createObjectFromArray(array $data): static;
    
}