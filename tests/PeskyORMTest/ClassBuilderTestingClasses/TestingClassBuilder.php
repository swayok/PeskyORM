<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\ClassBuilderTestingClasses;

use PeskyORM\ORM\ClassBuilder\ClassBuilder;

class TestingClassBuilder extends ClassBuilder
{
    private string $classesPrefix;

    public function setClassesPrefix(string $prefix): void
    {
        $this->classesPrefix = $prefix;
    }

    protected function getClassName(string $type): string
    {
        return $this->classesPrefix . parent::getClassName($type);
    }
}