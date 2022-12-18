<?php

declare(strict_types=1);

namespace PeskyORM\ORM\RecordsCollection;

use PeskyORM\Select\SelectQueryBuilderInterface;

interface SelectedRecordsCollectionInterface extends RecordsCollectionInterface
{
    public function getSelect(): SelectQueryBuilderInterface;
}
