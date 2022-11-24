<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\ValueToObjectConverter;

abstract class ValueToObjectConverter implements ValueToObjectConverterInterface
{
    use ConvertsArrayToObject;
    
    public array $other = [];
    
    public function handleUnknownArrayKeys(array $unknownProperties, array $data): void
    {
        $this->other = $unknownProperties;
    }
    
}