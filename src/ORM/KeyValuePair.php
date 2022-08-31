<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

class KeyValuePair
{
    
    protected string|int|bool|float $key;
    protected mixed $value;
    
    public static function create(float|bool|int|string $key, mixed $value): static
    {
        return new static($key, $value);
    }
    
    public function __construct(float|bool|int|string $key, mixed $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
    
    public function getKey(): float|bool|int|string
    {
        return $this->key;
    }
    
    public function getValue(): mixed
    {
        return $this->value;
    }
    
}