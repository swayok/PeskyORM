<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbImageFileInfo;
use PeskyORM\Exception\DbObjectFieldException;
use Swayok\Utils\Exception\ImageUtilsException;
use Swayok\Utils\ImageUtils;

class ImageField extends FileField {

    /**
     * @var string
     */
    protected $fileInfoClassName = 'PeskyORM\DbImageFileInfo';

    public function isValidValueFormat($value, $silent = true) {
        if (empty($value)) {
            return true;
        }
        if (!parent::isValidValueFormat($value, $silent)) {
            return false;
        }
        if (!ImageUtils::isImage($value)) {
            $this->setValidationError('Uploaded file is not image or image type is not supported', !$silent);
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
     * @param string $versionName
     * @return bool|string
     * @throws DbObjectFieldException
     */
    public function restoreImageVersion($versionName, $ext = null) {
        $configs = $this->getImageVersionsConfigs();
        if (empty($configs[$versionName])) {
            return false;
        }
        return ImageUtils::restoreVersionForConfig(
            $versionName,
            $configs[$versionName],
            $this->getFileNameWithoutExtension(),
            $this->getFileDirPath(),
            $ext
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
     * @param DbImageFileInfo $fileInfo
     * @return bool
     */
    protected function storeFileToFS($uploadedFileInfo, $pathToFiles, $fileInfo) {
        try {
            $filesNames = ImageUtils::resize(
                $uploadedFileInfo,
                $pathToFiles,
                $fileInfo->getFileNameWithoutExtension(),
                $this->getImageVersionsConfigs()
            );
        } catch (ImageUtilsException $exc) {
            $this->setValidationError($exc->getMessage());
            return false;
        }
        $fileInfo->setFilesNames($filesNames);
        return $fileInfo;
    }
}