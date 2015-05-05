<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Exception\DbObjectFieldException;
use Swayok\Utils\ImageUtils;

class ImageField extends FileField {

    /**
     * @var string
     */
    protected $fileInfoClassName = 'PeskyORM\DbFileInfo';

    public function isValidValueFormat($value) {
        if (empty($value)) {
            return true;
        }
        if (!parent::isValidValueFormat($value)) {
            return false;
        }
        if (!ImageUtils::isImage($value)) {
            $this->setValidationError('Uploaded file is not image or image type is not supported');
            return false;
        }
        return true;
    }

    /**
     * Get urls to image versions
     * @return array
     * @throws DbObjectFieldException
     */
    public function getImagesUrls() {
        if (!$this->dbObject->exists()) {
            throw new DbObjectFieldException($this, 'Unable to get images urls of non-existing object');
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
     * @throws DbObjectFieldException
     */
    public function getImagesPaths() {
        if (!$this->dbObject->exists()) {
            throw new DbObjectFieldException($this, 'Unable to get images paths of non-existing object');
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
     * Restore image version by name
     * @param string $fileNameToRestore
     * @return bool|string - false: fail | string: file path
     * @throws DbObjectFieldException
     */
    public function restoreImageVersionByFileName($fileNameToRestore) {
        // find resize profile
        return ImageUtils::restoreVersion(
            $fileNameToRestore,
            $this->getFileNameWithoutExtension(),
            $this->getFileDirPath(),
            $this->getImageVersions()
        );
    }

    /**
     * @return array
     * @throws DbObjectFieldException
     */
    public function getImageVersions() {
        // todo: implement getFilesExtension
//        throw new DbObjectFieldException($this, "getImageVersions() not implemented yet");
        return array();
//        return isset($this->_model->fields[$field]['resize_settings']) ? $this->_model->fields[$field]['resize_settings'] : array()
    }
}