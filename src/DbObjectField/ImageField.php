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
     * Get urls to images
     * @param string $fieldName
     * @return array
     */
    public function getImagesUrl($fieldName) {
        $images = array();
        if (!empty($fieldName) && $this->exists() && $this->_getFileField($fieldName)) {
            $images = ImageUtils::getVersionsUrls(
                $this->getFileDirPath($fieldName),
                $this->buildBaseUrlToFiles($fieldName),
                $this->getBaseFileName($fieldName),
                $this->_getFileField($fieldName)->getImageVersions()
            );
        }
        return $images;
    }

    /**
     * Get fs paths to images
     * @param string $fieldName
     * @return array
     */
    public function getImagesPaths($fieldName) {
        $images = array();
        if (!empty($fieldName) && $this->exists() && $this->_getFileField($fieldName)) {
            $images = ImageUtils::getVersionsPaths(
                $this->getFileDirPath($fieldName),
                $this->getBaseFileName($fieldName),
                $this->_getFileField($fieldName)->getImageVersions()
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
            $this->values['file_path'] = $this->getImagesPaths($this->getName());
        }
        return $this->values['file_path'];
    }

    /**
     * @return bool
     */
    public function isFileExists() {
        $path = $this->getFilePath();
        return file_exists($path['original']);
    }

    /**
     * Format file info
     * @param $value
     * @return array - if image uploaded - image inf, else - urls to image versions
     */
    protected function formatFile($value) {
        if (!is_array($value) || !isset($value['tmp_name'])) {
            $value = $this->getImagesUrl($this->getName());
            $this->setValueReceivedFromDb(true);
        }
        return $value;
    }

    /**
     * Restore image version by name
     * @param string $fileName
     * @return bool|string - false: fail | string: file path
     */
    public function restoreImageVersionByFileName($fileName) {
        if ($this->isImage() && !empty($fileName)) {
            // find resize profile
            return ImageUtils::restoreVersion(
                $fileName,
                $this->getBaseFileName(),
                $this->getFileDirPath(),
                $this->dbColumnConfig['resize_settings']
            );
        }
        return false;
    }

    /**
     * @return array
     * @throws DbFieldException
     */
    public function getImageVersions() {
        // todo: implement getFilesExtension
        throw new DbFieldException($this, "getImageVersions() not implemented yet");
        return array();
//        return isset($this->_model->fields[$field]['resize_settings']) ? $this->_model->fields[$field]['resize_settings'] : array()
    }
}