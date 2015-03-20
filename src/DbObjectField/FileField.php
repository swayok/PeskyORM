<?php


namespace ORM\DbObjectField;

use PeskyORM\DbObjectField;
use PeskyORM\Lib\Utils;

class FileField extends DbObjectField {

    protected function doBasicValueValidationAndConvertion($value) {
        return $this->formatFile($value);
    }

    /**
     * @return bool
     * @throws \PeskyORM\Exception\DbFieldException
     */
    public function isUploadedFile() {
        return (!$this->isValueReceivedFromDb() && is_array($this->getValue()) && Utils::isUploadedFile($this->getValue()));
    }

    /**
     * Get fs path to file
     * @return mixed
     */
    protected function getFilePath() {
        if (!isset($this->values['file_path'])) {
            $this->values['file_path'] = $this->dbObject->getFilePath($this->getName());
        }
        return $this->values['file_path'];
    }

    /**
     * @return bool
     */
    protected function isFileExists() {
        return file_exists($this->getFilePath());
    }

    /**
     * Format file info
     * @param $value
     * @return array - if image uploaded - image inf, else - urls to image versions
     */
    protected function formatFile($value) {
        if (!is_array($value) || !isset($value['tmp_name'])) {
            $value = $this->dbObject->getFileUrl($this->getName());
            $this->setValueReceivedFromDb(true);
        }
        return $value;
    }
}