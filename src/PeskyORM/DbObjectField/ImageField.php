<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Exception\DbObjectFieldException;
use Swayok\Utils\Exception\ImageUtilsException;
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
                $this->getImageVersionsConfigs()
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
                $this->getImageVersionsConfigs()
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
            $this->getImageVersionsConfigs()
        );
    }

    /**
     * @return array
     * @throws DbObjectFieldException
     */
    public function getImageVersionsConfigs() {
        return $this->getConfig()->getImageVersionsConfigs();
    }

    /**
     * Store image to FS + add info about image versions
     * @param array $uploadedFileInfo
     * @param string $pathToFiles
     * @param array $fileInfo
     * @return bool
     */
    protected function storeFileToFS($uploadedFileInfo, $pathToFiles, $fileInfo) {
        try {
            $filesNames = ImageUtils::resize(
                $uploadedFileInfo,
                $pathToFiles,
                $fileInfo['file_name'],
                $this->getImageVersionsConfigs()
            );
        } catch (ImageUtilsException $exc) {
            $this->setValidationError($exc->getMessage());
            return false;
        }
        $fileInfo['files_names'] = $filesNames;
        return $fileInfo;
    }
}