<?php

namespace PeskyORM\ORM;

use Swayok\Utils\File;

class DbFileInfo {

    /**
     * @var null|FileField|ImageField
     */
    protected $fileField = null;
    protected $fileExtension = null;
    protected $fileNameWithoutExtension = null;
    protected $fileNameWithExtension = null;

    protected $jsonMap = array(
        'file_name' => 'fileNameWithoutExtension',
        'full_file_name' => 'fileNameWithExtension',
        'ext' => 'fileExtension',
    );

    /**
     * DbFileInfo constructor.
     * @param FileField $fileField
     */
    public function __construct(FileField $fileField) {
        $this->fileField = $fileField;
    }

    public function readFromFileOrAutodetect() {
        if ($this->fileField->getDbObject()->exists()) {
            $infoFilePath = $this->fileField->getInfoFilePath();
            if (File::exist($infoFilePath)) {
                $info = File::readJson($infoFilePath);
                if (!empty($info)) {
                    $this->update($info);
                }
            }
        }
        return $this;
    }

    public function saveToFile() {
        if (!$this->fileField->getDbObject()->exists()) {
            throw new DbObjectFieldException($this->fileField, 'Unable to save file info json file of non-existing object');
        }
        $data = array();
        foreach ($this->jsonMap as $jsonKey => $paramName) {
            $method = 'get' . ucfirst($paramName);
            $value = $this->$method();
            if ($value !== null) {
                $data[$jsonKey] = $value;
            }
        }
        $infoFilePath = $this->fileField->getInfoFilePath();
        File::saveJson($infoFilePath, $data, true);
    }

    /**
     * @param array $data
     * @return $this
     */
    public function update($data) {
        foreach ($this->jsonMap as $jsonKey => $paramName) {
            if (array_key_exists($jsonKey, $data) && $data[$jsonKey] !== null) {
                $method = 'set' . ucfirst($paramName);
                $this->$method($data[$jsonKey]);
            }
        }
        return $this;
    }

    /**
     * @return null
     */
    public function getFileExtension() {
        return $this->fileExtension;
    }

    /**
     * @param null $extension
     * @return $this
     */
    public function setFileExtension($extension) {
        $this->fileExtension = $extension;
        return $this;
    }

    /**
     * @return null
     */
    public function getFileNameWithoutExtension() {
        return $this->fileNameWithoutExtension;
    }

    /**
     * @param null $fileNameWithoutExtension
     * @return $this
     */
    public function setFileNameWithoutExtension($fileNameWithoutExtension) {
        $this->fileNameWithoutExtension = $fileNameWithoutExtension;
        return $this;
    }

    /**
     * @return null
     */
    public function getFileNameWithExtension() {
        return $this->fileNameWithExtension;
    }

    /**
     * @param null $fileNameWithExtension
     * @return $this
     */
    public function setFileNameWithExtension($fileNameWithExtension) {
        $this->fileNameWithExtension = $fileNameWithExtension;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilePath() {
        return $this->fileField->getFilePath();
    }

    /**
     * @return string
     * @throws DbObjectFieldException
     */
    public function getAbsoluteFileUrl() {
        return $this->fileField->getAbsoluteFileUrl();
    }

    /**
     * @return bool
     */
    public function isFileExists() {
        return $this->fileField->isFileExists();
    }

    /**
     * @return array
     */
    public function toArray() {
        return array(
            'path' => $this->getFilePath(),
            'url' => $this->getAbsoluteFileUrl(),
            'file_name' => $this->getFileNameWithoutExtension(),
            'full_file_name' => $this->getFileNameWithExtension(),
            'ext' => $this->getFileExtension(),
        );
    }

}