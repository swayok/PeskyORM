<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

interface PasswordColumnInterface extends TableColumnInterface
{
    public function hashPassword(string $value): string;

    public function verifyPassword(string $plainValue, string $hashedValue): bool;

    public function normalizePasswordString(string $value, bool $isFromDb): ?string;
}