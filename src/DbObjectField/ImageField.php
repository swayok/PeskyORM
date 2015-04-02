<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Exception\DbFieldException;
use PeskyORM\Lib\ImageUtils;

class ImageField extends FileField {

    public function isValidValueFormat($value) {
        if (empty($value) || parent::isValidValueFormat($value) && ImageUtils::isImage($value)) {
            return true;
        }
        $this->setValidationError('Uploaded file is not image or image type is not supported');
        return false;
    }

    /**
     * Format file info
     * @param $value
     * @return array - if image uploaded - image inf, else - urls to image versions
     */
    protected function formatFile($value) {
        if (!is_array($value) || !isset($value['tmp_name'])) {
            $value = $this->getImagesUrls();
            $this->setValueReceivedFromDb(true);
        }
        return $value;
    }

    /**
     * Get urls to image versions
     * @return array
     * @throws DbFieldException
     */
    public function getImagesUrls() {
        if (!$this->dbObject->exists()) {
            throw new DbFieldException($this, 'Unable to get images urls of non-existing object');
        }
        $images = array();
        if ($this->dbObject->exists()) {
            $images = ImageUtils::getVersionsUrls(
                $this->getFileDirPath(),
                $this->getFileDirRelativeUrl(),
                $this->getFileNameWithoutExtension(),
                $this->getImageVersions()
            );
        }
        return $images;
    }

    /**
     * Get fs paths to image file versions
     * @return array
     * @throws DbFieldException
     */
    public function getImagesPaths() {
        if (!$this->dbObject->exists()) {
            throw new DbFieldException($this, 'Unable to get images paths of non-existing object');
        }
        $images = array();
        if ($this->dbObject->exists()) {
            $images = ImageUtils::getVersionsPaths(
                $this->getFileDirPath(),
                $this->getFileNameWithoutExtension(),
                $this->getImageVersions()
            );
        }
        return $images;
    }

    /**
     * Get fs path to file
     * @return mixed
     */
    public function getFilePath() {
        if (!isset($this->values['file_path'])) {
            $this->values['file_path'] = $this->getImagesPaths();
        }
        return $this->values['file_path'];
    }

    /**
     * Restore image version by name
     * @return bool|string - false: fail | string: file path
     */
    public function restoreImageVersionByFileName() {
        // find resize profile
        return ImageUtils::restoreVersion(
            $this->getName(),
            $this->getFileNameWithoutExtension(),
            $this->getFileDirPath(),
            $this->dbColumnConfig['resize_settings']
        );
    }

    /**
     * @return array
     * @throws DbFieldException
     */
    public function getImageVersions() {
        // todo: implement getFilesExtension
//        throw new DbFieldException($this, "getImageVersions() not implemented yet");
        return array();
//        return isset($this->_model->fields[$field]['resize_settings']) ? $this->_model->fields[$field]['resize_settings'] : array()
    }
}