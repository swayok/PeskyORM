<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\ORM\Traits\ConvertsArrayToObject;

abstract class ValueToObjectConverter implements ValueToObjectConverterInterface
{
    
    use ConvertsArrayToObject;
    
    public array $other = [];
    
    public function handleUnknownArrayKeys(array $unknownProperties, array $data): void
    {
        $this->other = $unknownProperties;
    }
    
}