<?php

namespace PeskyORM;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordValue;
use PeskyORM\ORM\TableStructure;
use Swayok\Utils\StringUtils;

class DbColumnConfig extends Column {

    /**
     * @deprecated
     * @return DbTableConfig|TableStructure
     */
    public function getDbTableConfig() {
        return $this->getTableStructure();
    }

    /**
     * @deprecated
     * @param DbTableConfig $dbTableConfig
     * @return $this
     */
    public function setDbTableConfig(DbTableConfig $dbTableConfig) {
        return $this->setTableStructure($dbTableConfig);
    }

    /**
     * @deprecated
     * Get full name of class that extends DbObjectField class
     * @param string $defaultNamespace - namespace for hardcoded column types
     * @return string
     */
    public function getClassName($defaultNamespace) {
        return rtrim($defaultNamespace, '\\') . '\\' . StringUtils::classify($this->getType()) . 'Field';
    }

    /**
     * @deprecated
     * @param int $maxLength - 0 = unlimited
     * @return $this
     */
    public function setMaxLength($maxLength) {
        return $this;
    }

    /**
     * @deprecated
     * @param int $minLength - 0 = unlimited
     * @return $this
     */
    public function setMinLength($minLength) {
        return $this;
    }

    /**
     * @deprecated
     * @return boolean
     */
    public function isNullable() {
        return $this->isValueCanBeNull();
    }

    /**
     * @deprecated
     * @param boolean $isNullable
     * @return $this
     */
    public function setIsNullable(bool $isNullable) {
        return $isNullable ? $this->allowsNullValues() : $this->disallowsNullValues();
    }

    /**
     * @deprecated
     * @param bool $trimValue
     * @return $this
     */
    public function setTrimValue($trimValue = true) {
        $this->trimValue = $trimValue;
        return $this;
    }

    /**
     * @return bool
     * @deprecated
     */
    public function isTrimValue() {
        return $this->isValueTrimmingRequired();
    }

    /**
     * @deprecated
     * @return mixed
     */
    public function getDefaultValue() {
        return $this->getValidDefaultValue();
    }

    /**
     * @deprecated
     * @return boolean
     */
    public function isConvertEmptyValueToNull() {
        return $this->isEmptyStringMustBeConvertedToNull();
    }

    /**
     * @deprecated
     * @param boolean $convertEmptyValueToNull
     * @return $this
     */
    public function setConvertEmptyValueToNull(bool $convertEmptyValueToNull) {
        $this->convertEmptyStringToNull = $convertEmptyValueToNull;
        return $this;
    }

    /**
     * @deprecated
     * @param string $action - self::ON_UPDATE or self::ON_CREATE
     * @return bool
     */
    public function isRequiredOn($action) {
        return $this->isValueRequiredToBeNotEmpty();
    }

    /**
     * @deprecated
     * @return bool
     */
    public function isRequiredOnAnyAction() {
        return $this->isValueRequiredToBeNotEmpty();
    }

    /**
     * @deprecated
     * @return bool
     */
    public function getIsRequired() {
        return $this->isValueRequiredToBeNotEmpty();
    }

    /**
     * @deprecated - duplicates is nullable
     * @param int|bool $isRequired
     * @return $this
     */
    public function setIsRequired($isRequired) {
        return $this;
    }

    /**
     * @deprecated
     * @return boolean
     */
    public function isPk() {
        return $this->isItPrimaryKey();
    }

    /**
     * @deprecated
     * @param boolean $isPk
     * @return $this
     */
    public function setIsPk(bool $isPk) {
        $this->isPrimaryKey = $isPk;
        return $this;
    }

    /**
     * @deprecated
     * @return boolean
     */
    public function isUnique() {
        return $this->isValueMustBeUnique();
    }

    /**
     * @deprecated
     * @param boolean $isUnique
     * @return $this
     */
    public function setIsUnique(bool $isUnique) {
        if ($isUnique) {
            return $this->uniqueValues();
        } else {
            $this->isValueMustBeUnique = false;
            return $this;
        }
    }

    /**
     * @deprecated
     * @return boolean
     */
    public function isVirtual() {
        return $this->isItExistsInDb();
    }

    /**
     * @deprecated - replaced by value getter
     * @return bool|string - false: don't import
     */
    public function importVirtualColumnValueFrom() {
        return false;
    }

    /**
     * @deprecated - use value getter directly
     * @param string $columnName
     * @return $this
     */
    public function setImportVirtualColumnValueFrom(string $columnName) {
        if (!is_string($columnName)) {
            throw new \InvalidArgumentException("Argument \$columnName in setImportVirtualColumnValueFrom() must be a string. Passed value: [{$columnName}]");
        }
        if ($this->getTableStructure() && !$this->getTableStructure()->hasColumn($columnName)) {
            throw new \InvalidArgumentException("Column [{$columnName}] is not defined");
        }
        $this->setValueGetter(function (RecordValue $value, $format = null) use ($columnName) {
            return $value->getRecord()->getValue($columnName, $format);
        });
        return $this;
    }

    /**
     * @deprecated - use doesNotExistInDb()
     * @param boolean $isVirtual
     * @return $this
     */
    public function setIsVirtual(bool $isVirtual) {
        $this->existsInDb = $isVirtual;
        return $this;
    }

    /**
     * @deprecated
     * @param string $action - self::ON_UPDATE or self::ON_CREATE
     * @return bool
     */
    public function isExcludedOn($action) {
        return $this->isValueCanBeSetOrChanged();
    }

    /**
     * @deprecated
     * @return bool
     */
    public function isExcludedOnAnyAction() {
        return $this->isValueCanBeSetOrChanged();
    }

    /**
     * @deprecated
     * @return bool
     */
    public function getIsExcluded(): bool {
        return $this->isValueCanBeSetOrChanged();
    }

    /**
     * @deprecated use valueCannotBeSetOrChanged()
     * @param bool $isExcluded
     * @return $this
     */
    public function setIsExcluded(bool $isExcluded) {
        $this->isValueCanBeSetOrChanged = false;
        return $this;
    }

    /**
     * @deprecated
     * @return boolean
     */
    public function isFile() {
        return $this->isItAFile();
    }

    /**
     * @deprecated - use itIsFile()
     * @param boolean $isFile
     */
    protected function setIsFile(bool $isFile) {
        $this->isFile = $isFile;
    }

    /**
     * @deprecated
     * @return boolean
     */
    public function isImage() {
        return $this->isItAnImage();
    }

    /**
     * @deprecated use itIsImage()
     * @param boolean $isImage
     */
    protected function setIsImage(bool $isImage) {
        $this->isImage = $isImage;
    }

    /**
     * @deprecated
     * @return boolean
     */
    public function isFk() {
        return $this->isItAForeignKey();
    }

    /**
     * @deprecated - use itIsForeignKey()
     * @param boolean $isFk
     */
    protected function setIsFk($isFk) {
        $this->itIsForeignKey(null);
    }

    /**
     * @deprecated
     * Is this column exists in DB?
     * @return bool
     */
    public function isExistsInDb() {
        return !$this->isItExistsInDb();
    }

    /**
     * @deprecated - use setValueValidator()
     * @param array $customValidators - should contain only callable values.
     * Callable:
        function (DbObjectField $dbObjectField, $value) {
            //$dbObjectField->setValidationError('Invalid value');
            return true;
        }
     * @return $this
     */
    public function setCustomValidators($customValidators) {
        return $this;
    }

    /**
     * @deprecated
     * @return boolean
     */
    public function isPrivate() {
        return $this->isValuePrivate();
    }

    /**
     * @deprecated
     * @param boolean $isPrivate
     * @return $this
     */
    public function setIsPrivate(bool $isPrivate) {
        $this->isPrivate = $isPrivate;
        return $this;
    }

}