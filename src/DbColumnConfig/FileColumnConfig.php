<?php


namespace PeskyORM\DbColumnConfig;


use PeskyORM\DbColumnConfig;
use PeskyORM\Exception\DbColumnConfigException;

class FileColumnConfig extends DbColumnConfig {

    /** @var string */
    protected $basePathToFiles;
    /** @var string|null */
    protected $baseUrlToFiles = null;
    /**
     * @var array|null - null: any extension
     */
    protected $allowedFileExtensions = null;
    /**
     * @var string|null - null: no default extension
     */
    protected $defaultFileExtension = null;
    /**
     * @var callable|null
     * function (FileField $field) {}
     */
    protected $fileNameGenerator = null;
    /**
     * @var callable|null
     * function (FileField $field) {}
     */
    protected $fileSubdirGenerator = null;
    /**
     * @var callable|null
     * function (FileField $field) {}
     */
    protected $filePathGenerator = null;
    /**
     * @var callable|null
     * function (FileField $field) {}
     */
    protected $fileUrlGenerator = null;

    /**
     * @param string $name
     * @param string|null $basePathToFiles
     * @param string|null $baseUrlToFiles
     * @return EnumColumnConfig
     */
    static public function create($name, $basePathToFiles = null, $baseUrlToFiles = null) {
        return new EnumColumnConfig($name, $basePathToFiles, $baseUrlToFiles);
    }

    /**
     * @param string $name
     * @param string|null $basePathToFiles
     * @param string|null $baseUrlToFiles
     */
    public function __construct($name, $basePathToFiles = null, $baseUrlToFiles = null) {
        parent::__construct($name, self::TYPE_FILE);
        if (!empty($basePathToFiles)) {
            $this->setBasePathToFiles($basePathToFiles);
        }
        if (!empty($baseUrlToFiles)) {
            $this->setBasePathToFiles($baseUrlToFiles);
        }
    }

    /**
     * @throws DbColumnConfigException
     */
    public function validateConfig() {
        if (empty($this->basePathToFiles)) {
            throw new DbColumnConfigException($this, '$basePathToFiles is required to be not-empty string');
        }
    }

    /**
     * @return string
     */
    public function getBasePathToFiles() {
        return $this->basePathToFiles;
    }

    /**
     * @param string $basePathToFiles
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setBasePathToFiles($basePathToFiles) {
        if (empty($basePathToFiles) || !is_string($basePathToFiles)) {
            throw new DbColumnConfigException($this, '$basePathToFiles is required to be not-empty string');
        }
        $this->basePathToFiles = $basePathToFiles;
        return $this;
    }

    /**
     * @return string
     * @throws DbColumnConfigException
     */
    public function getBaseUrlToFiles() {
        if ($this->baseUrlToFiles === null) {
            throw new DbColumnConfigException($this, '$baseUrlToFiles is not provided');
        }
        return $this->baseUrlToFiles;
    }

    /**
     * @param string|null $baseUrlToFiles
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setBaseUrlToFiles($baseUrlToFiles) {
        if (!is_string($baseUrlToFiles)) {
            throw new DbColumnConfigException($this, '$baseUrlToFiles should be a string');
        }
        $this->baseUrlToFiles = $baseUrlToFiles;
        return $this;
    }


}