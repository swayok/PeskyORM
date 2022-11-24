<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\ValueToObjectConverter;

interface ValueToObjectConverterInterface
{
    
    public static function createObjectFromArray(array $data): static;
    
}