<?php


namespace PeskyORM;


use PeskyORM\DbObjectField\FileField;
use PeskyORM\DbObjectField\ImageField;
use PeskyORM\Exception\DbObjectFieldException;
use Swayok\Utils\File;

class DbFileInfo {

    /**
     * @var null|FileField|ImageField
     */
    protected $fileField = null;
    protected $fileExtension = null;
    protected $fileNameWithoutExtension = null;
    protected $fileNameWithExtension = null;
    protected $originalFileNameWithExtension = null;
    protected $originalFileNameWithoutExtension = null;

    protected $jsonMap = array(
        'file_name' => 'fileNameWithoutExtension',
        'full_file_name' => 'fileNameWithExtension',
        'original_file_name' => 'originalFileNameWithoutExtension',
        'original_full_file_name' => 'originalFileNameWithExtension',
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
        $this->fileNameWithoutExtension = rtrim($fileNameWithoutExtension, '.');
        return $this;
    }

    /**
     * @return string
     */
    public function getFileNameWithExtension() {
        return $this->fileNameWithExtension;
    }

    /**
     * @param string $fileNameWithExtension
     * @return $this
     */
    public function setFileNameWithExtension($fileNameWithExtension) {
        $this->fileNameWithExtension = rtrim($fileNameWithExtension, '.');
        return $this;
    }

    /**
     * @return string
     */
    public function getOriginalFileNameWithExtension() {
        return empty($this->originalFileNameWithExtension)
            ? $this->getFileNameWithExtension()
            : $this->originalFileNameWithExtension;
    }

    /**
     * @param string $fileNameWithExtension
     * @return $this
     */
    public function setOriginalFileNameWithExtension($fileNameWithExtension) {
        $this->originalFileNameWithExtension = rtrim($fileNameWithExtension, '.');
        return $this;
    }

    /**
     * @return string
     */
    public function getOriginalFileNameWithoutExtension() {
        return empty($this->originalFileNameWithoutExtension)
            ? $this->getFileNameWithoutExtension()
            : $this->originalFileNameWithoutExtension;
    }

    /**
     * @param string $fileNameWithoutExtension
     * @return $this
     */
    public function setOriginalFileNameWithoutExtension($fileNameWithoutExtension) {
        $this->originalFileNameWithoutExtension = rtrim($fileNameWithoutExtension, '.');
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
    public function toPublicArray() {
        return array(
            'path' => $this->getFilePath(),
            'url' => $this->getAbsoluteFileUrl(),
            'file_name' => $this->getFileNameWithoutExtension(),
            'full_file_name' => $this->getFileNameWithExtension(),
            'ext' => $this->getFileExtension(),
        );
    }

}