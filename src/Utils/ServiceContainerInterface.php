<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

use PeskyORM\Exception\ServiceContainerException;
use Psr\Container\ContainerInterface;

interface ServiceContainerInterface extends ContainerInterface
{
    /**
     * Register an instance of abstract.
     * $instance can be a closure that creates an instance on demand and returns it.
     * Closure is used only once.
     * If $instance is string - it must point to an existing class.
     * When instance requested - container will create an isntance of this class once.
     */
    public function instance(
        string $abstract,
        string|object|null $instance = null
    ): static;

    /**
     * Register a binding for abstract.
     * $abstract is expected to be an abstract class or an interface.
     * $concrete is expected to be a concrete class or a closure that returns a concrete class.
     * $singleton used to mark binding as single instance only. When true: get() and make()
     * will not create more than 1 instance of $concrete class.
     */
    public function bind(
        string $abstract,
        \Closure|string|null $concrete = null,
        bool $singleton = false
    ): static;

    /**
     * Remove a binding for abstract.
     */
    public function unbind(string $abstract): static;

    /**
     * Register an alias name for abstract.
     */
    public function alias(string $abstract, string $alias): static;

    /**
     * Create a concrete instance of a registered abstract.
     * Singletons will have only 1 instance no matter how many times this method is called.
     * $parameters will not be used for already instantiated singletons.
     * Note: if abstract is not registered - this method will register it and try
     * to make an instance.
     * @throws ServiceContainerException when class not exists or cannot be instantiated.
     */
    public function make(
        string $abstract,
        array $parameters = []
    ): mixed;
}