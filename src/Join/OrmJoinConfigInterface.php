<?php

declare(strict_types=1);

namespace PeskyORM\Join;

use PeskyORM\ORM\Table\TableInterface;

interface OrmJoinConfigInterface extends NormalJoinConfigInterface
{
    public function getForeignTable(): TableInterface;
}