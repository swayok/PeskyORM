<?php

declare(strict_types=1);

namespace PeskyORM\Core;

interface CrossJoinConfigInterface extends JoinConfigInterface
{
    public function getJoinQuery(): DbExpr;
}