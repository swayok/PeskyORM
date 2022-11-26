<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription;

use PeskyORM\DbExpr;

interface ColumnDescriptionInterface extends \Serializable
{
    public function getName(): string;

    public function getDbType(): string;

    public function getOrmType(): string;

    public function getLimit(): ?int;

    public function getNumberPrecision(): ?int;

    public function isNullable(): bool;

    public function getDefault(): DbExpr|float|bool|int|string|null;

    public function isPrimaryKey(): bool;

    public function isForeignKey(): bool;

    public function isUnique(): bool;
}