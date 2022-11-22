<?php

declare(strict_types=1);

namespace PeskyORM\Join;

use PeskyORM\DbExpr;

interface CrossJoinConfigInterface extends JoinConfigInterface
{
    public function getJoinQuery(): DbExpr;
}