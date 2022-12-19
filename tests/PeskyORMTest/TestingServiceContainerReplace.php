<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest;

use PeskyORM\Utils\ServiceContainerInterface;

class TestingServiceContainerReplace implements ServiceContainerInterface
{
    public function get(string $id): string
    {
        return $id;
    }

    public function has(string $id): bool
    {
        return true;
    }

    public function instance(
        string $abstract,
        object|string|null $instance = null
    ): static {
        return $this;
    }

    public function bind(
        string $abstract,
        string|\Closure|null $concrete = null,
        bool $singleton = false
    ): static {
        return $this;
    }

    public function unbind(string $abstract): static
    {
        return $this;
    }

    public function alias(string $abstract, string $alias): static
    {
        return $this;
    }

    public function make(
        string $abstract,
        array $parameters = []
    ): string {
        return $abstract;
    }
}