<?php

declare(strict_types=1);

namespace PeskyORM\Join;

use PeskyORM\Utils\ArgumentValidators;

class JoinConfig extends NormalJoinConfigAbstract
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $joinName,
        string $joinType,
        string $localTableAlias,
        string $localColumnName,
        string $foreignTableName,
        string $foreignColumnName,
        ?string $foreignTableSchema = null
    ) {
        parent::__construct($joinName, $joinType);
        $this->setLocalTableAlias($localTableAlias)
            ->setLocalColumnName($localColumnName)
            ->setForeignTableName($foreignTableName)
            ->setForeignColumnName($foreignColumnName)
            ->setForeignTableSchema($foreignTableSchema);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function setForeignTableName(string $foreignTableName): static
    {
        ArgumentValidators::assertNotEmpty('$foreignTableName', $foreignTableName);
        $this->foreignTableName = $foreignTableName;
        return $this;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setForeignTableSchema(?string $schema): static
    {
        ArgumentValidators::assertNullOrNotEmptyString('$schema', $schema);
        $this->foreignTableSchema = $schema;
        return $this;
    }
}
