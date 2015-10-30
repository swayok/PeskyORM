<?php


namespace PeskyORM;

use PeskyORM\DbObjectField\ImageField;

class DbImageFileInfo extends DbFileInfo {

    protected $filesNames = array();

    /**
     * DbFileInfo constructor.
     * @param ImageField $fileField
     */
    public function __construct(ImageField $fileField) {
        $this->jsonMap['files_names'] = 'filesNames';
        parent::__construct($fileField);
    }

    /**
     * @return array
     */
    public function getFilesNames() {
        return $this->filesNames;
    }

    /**
     * @param array $filesNames
     * @return $this
     */
    public function setFilesNames($filesNames) {
        $this->filesNames = $filesNames;
        return $this;
    }

    /**
     * @param null|string $versionName
     * @return string|array
     * @throws Exception\DbObjectFieldException
     */
    public function getFilePath($versionName = null) {
        return $this->fileField->getImageVersionPath($versionName);
    }

    /**
     * @param null|string $versionName
     * @return string
     * @throws Exception\DbObjectFieldException
     */
    public function getAbsoluteFileUrl($versionName = null) {
        return $this->fileField->getAbsoluteFileUrl($versionName);
    }

}