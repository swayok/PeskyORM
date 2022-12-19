<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFoundException extends ServiceContainerException implements NotFoundExceptionInterface
{

}