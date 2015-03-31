<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbFieldException;
use PeskyORM\Lib\Utils;

class FileField extends DbObjectField {

    public function isValidValueFormat($value) {
        if (empty($value) || !is_array($value) || array_key_exists('tmp_file', $value)) {
            return true;
        }
        $this->setValidationError('File upload expected');
        return false;
    }

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
    public function getFilePath() {
        if (!isset($this->values['file_path'])) {
            $this->values['file_path'] = $this->dbObject->getFilePath($this->getName());
        }
        return $this->values['file_path'];
    }

    /**
     * @return bool
     */
    public function isFileExists() {
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


    /**
     * @return string
     * @throws DbFieldException
     */
    public function getFilesBasePath() {
        // todo: implement getFilesBasePath
        // todo throw exception if base path not set or is empty
        throw new DbFieldException($this, "getFilesBasePath() not implemented yet");
        return '';
    }

    /**
     * @return string
     * @throws DbFieldException
     */
    public function getFilesSubdir() {
        // todo: implement getFilesSubdir
        throw new DbFieldException($this, "getFilesSubdir() not implemented yet");
        return '';
        //$subdir = !empty($this->_model->fields[$field]['subdir']) ? trim($this->_model->fields[$field]['subdir'], '/\\') . DIRECTORY_SEPARATOR : '';
    }

    /**
     * @param string|callable $fallbackValue
     * @return string
     * @throws DbFieldException
     */
    public function getFileName($fallbackValue) {
        // todo: implement hasCustomFileName
        // todo: throw exception when no file name specified and $fallbackValue is empty or not a string or callable
        throw new DbFieldException($this, "hasCustomFileName() not implemented yet");
        return '';
    }

    /**
     * @return string
     * @throws DbFieldException
     */
    public function getFilesBaseUrl() {
        // todo: implement getFilesBaseUrl
        throw new DbFieldException($this, "getFilesBaseUrl() not implemented yet");
        return '';
    }

    /**
     * @return string|null
     * @throws DbFieldException
     */
    public function getDefaultFileExtension() {
        // todo: implement getFilesExtension
        // note: extension could be array of accepted extensions
        // todo: separate accepted extesions and default extension
        throw new DbFieldException($this, "getFilesExtension() not implemented yet");
        return null;
        /*if (!empty($this->_model->fields[$field]['extension'])) {
            if (is_array($this->_model->fields[$field]['extension'])) {
                foreach ($this->_model->fields[$field]['extension'] as $ext) {
                    if (File::exist($pathTofiles . $baseName . '.' . $ext)) {
                        $baseName .= '.' . $ext;
                        break;
                    }
                }
            }
        }
        return $this->_model->fields[$field]['extension'];

        */
    }

    /**
     * @return array
     * @throws DbFieldException
     */
    public function getAllwedFileExtensions() {
        // todo: implement getAllwedFilesExtensions
        throw new DbFieldException($this, "getAllwedFilesExtensions() not implemented yet");
        return array();
    }
}