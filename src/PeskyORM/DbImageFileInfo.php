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
     * @return string
     */
    public function getFilePath() {
        return $this->fileField->getImagesPaths();
    }

    /**
     * @return string
     */
    public function getFileUrl() {
        return $this->fileField->getImagesUrls();
    }

}