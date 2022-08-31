<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

interface ValueToObjectConverterInterface
{
    
    public static function createObjectFromArray(array $data): static;
    
}