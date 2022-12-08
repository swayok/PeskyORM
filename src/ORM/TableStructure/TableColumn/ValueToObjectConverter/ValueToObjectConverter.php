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

    /**
     * Return data similar to incoming data.
     * $this->other removed from returned array having its values merged into it
     * @return array
     */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        unset($data['other']);
        return array_merge($data, $this->other);
    }
}