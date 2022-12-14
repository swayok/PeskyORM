<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

trait CanConvertEmptyStringValueToNull
{
    /**
     * Values:
     * = null - autodetect depending on $this->isNullableValues() value:
     *          - convert '' to null if null value allowed;
     *          - leave '' as is if null not allowed.
     * = true - always convert '' to null;
     * = false - always leave '' as is;
     */
    protected ?bool $convertEmptyStringValueToNull = null;

    public function convertsEmptyStringValuesToNull(): static
    {
        $this->convertEmptyStringValueToNull = true;
        return $this;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function shouldConvertEmptyStringToNull(bool $isFromDb): bool
    {
        return $this->convertEmptyStringValueToNull ?? $this->isNullableValues();
    }
}