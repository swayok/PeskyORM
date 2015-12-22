<?php


namespace PeskyORM\DbColumnConfig;


use PeskyORM\DbColumnConfig;
use PeskyORM\Exception\DbColumnConfigException;

class FileColumnConfig extends DbColumnConfig {

    protected $_type = self::TYPE_FILE;

    /** @var string|callable */
    protected $basePathToFiles;
    /** @var string|null|callable */
    protected $baseUrlToFiles = null;
    /**
     * @var array|null - null: any extension
     */
    protected $allowedFileExtensions = null;
    /**
     * @var string - null: no default extension
     */
    protected $defaultFileExtension = '';
    /**
     * @var callable|null
     * function (FileField $field) {}
     */
    protected $fileNameGenerator = null;
    /**
     * @var callable|null
     * function (FileField $field, $directorySeparator = DIRECTORY_SEPARATOR) {}
     */
    protected $fileSubdirGenerator = null;
    /**
     * @var callable|null
     * function (FileField $field) {}
     */
    protected $fileDirPathGenerator = null;
    /**
     * @var callable|null
     * function (FileField $field) {}
     */
    protected $fileDirRelativeUrlGenerator = null;
    /**
     * @var callable|null
     * function (FileField $field) {}
     */
    protected $fileServerUrlGenerator = null;

    /**
     * @param string $name
     * @param string|null $basePathToFiles
     * @param string|null $baseUrlToFiles
     * @return $this
     */
    static public function create($basePathToFiles = null, $baseUrlToFiles = null, $name = null) {
        $class = get_called_class();
        return new $class($name, $basePathToFiles, $baseUrlToFiles);
    }

    /**
     * @param string $name
     * @param string|null $basePathToFiles
     * @param string|null $baseUrlToFiles
     */
    public function __construct($name, $basePathToFiles = null, $baseUrlToFiles = null) {
        parent::__construct($name, $this->_type);
        if (!empty($basePathToFiles)) {
            $this->setBasePathToFiles($basePathToFiles);
        }
        if (!empty($baseUrlToFiles)) {
            $this->setBaseUrlToFiles($baseUrlToFiles);
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
        return is_callable($this->basePathToFiles) ? call_user_func($this->basePathToFiles) : $this->basePathToFiles;
    }

    /**
     * @param string|callable $basePathToFiles
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setBasePathToFiles($basePathToFiles) {
        if (empty($basePathToFiles) || (!is_string($basePathToFiles) && !is_callable($basePathToFiles))) {
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
        return is_callable($this->baseUrlToFiles) ? call_user_func($this->baseUrlToFiles) : $this->baseUrlToFiles;
    }

    /**
     * @param string|null|callable $baseUrlToFiles
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setBaseUrlToFiles($baseUrlToFiles) {
        if (!is_string($baseUrlToFiles) && !is_callable($baseUrlToFiles)) {
            throw new DbColumnConfigException($this, '$baseUrlToFiles should be a string');
        }
        if (!is_callable($baseUrlToFiles) && preg_match('%(http://[^/]+)(/.*$|$)%i', $baseUrlToFiles, $urlParts)) {
            $this->baseUrlToFiles = $urlParts[2];
            if (!$this->hasFileServerUrlGenerator()) {
                $baseUrl = $urlParts[1];
                $this->setFileServerUrlGenerator(function () use ($baseUrl) {
                    return $baseUrl;
                });
            }
        } else {
            $this->baseUrlToFiles = $baseUrlToFiles;
        }
        return $this;
    }

    /**
     * @return null|string
     */
    public function getDefaultFileExtension() {
        return $this->defaultFileExtension;
    }

    /**
     * @param null|string $defaultFileExtension
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setDefaultFileExtension($defaultFileExtension) {
        if (empty($defaultFileExtension) || !is_string($defaultFileExtension)) {
            throw new DbColumnConfigException($this, '$defaultFileExtension must be not-empty string');
        }
        $allowedExtensions = $this->getAllowedFileExtensions();
        if (!empty($allowedExtensions) && !in_array($defaultFileExtension, $allowedExtensions)) {
            throw new DbColumnConfigException($this, 'Provided $defaultFileExtension is not allowed');
        }
        $this->defaultFileExtension = $defaultFileExtension;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getAllowedFileExtensions() {
        return $this->allowedFileExtensions;
    }

    /**
     * @param array $allowedFileExtensions
     * @return $this
     * @throws DbColumnConfigException
     */
    public function setAllowedFileExtensions($allowedFileExtensions) {
        if (empty($allowedFileExtensions) || !is_array($allowedFileExtensions)) {
            throw new DbColumnConfigException($this, '$allowedFileExtensions must be not-empty array');
        }
        $defaultExtension = $this->getDefaultFileExtension();
        if (!empty($defaultExtension) && !in_array($defaultExtension, $allowedFileExtensions)) {
            throw new DbColumnConfigException($this, 'Provided $defaultFileExtension is not allowed');
        }
        $this->allowedFileExtensions = array_values($allowedFileExtensions);
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getFileDirRelativeUrlGenerator() {
        return $this->fileDirRelativeUrlGenerator;
    }

    /**
     * @param callable $fileDirRelativeUrlGenerator - function (FileField $field) {}
     * @return $this
     */
    public function setFileDirRelativeUrlGenerator(callable $fileDirRelativeUrlGenerator) {
        $this->fileDirRelativeUrlGenerator = $fileDirRelativeUrlGenerator;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getFileDirPathGenerator() {
        return $this->fileDirPathGenerator;
    }

    /**
     * @param callable $fileDirPathGenerator - function (FileField $field) {}
     * @return $this
     */
    public function setFileDirPathGenerator(callable $fileDirPathGenerator) {
        $this->fileDirPathGenerator = $fileDirPathGenerator;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getFileSubdirGenerator() {
        return $this->fileSubdirGenerator;
    }

    /**
     * @param callable $fileSubdirGenerator - function (FileField $field, $directorySeparator = DIRECTORY_SEPARATOR) {}
     * @return $this
     */
    public function setFileSubdirGenerator(callable $fileSubdirGenerator) {
        $this->fileSubdirGenerator = $fileSubdirGenerator;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getFileNameGenerator() {
        return $this->fileNameGenerator;
    }

    /**
     * @param callable $fileNameGenerator - function (FileField $field) {}
     * @return $this
     */
    public function setFileNameGenerator(callable $fileNameGenerator) {
        $this->fileNameGenerator = $fileNameGenerator;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getFileServerUrlGenerator() {
        return $this->fileServerUrlGenerator;
    }

    /**
     * @return bool
     */
    public function hasFileServerUrlGenerator() {
        return !empty($this->fileServerUrlGenerator);
    }

    /**
     * @param callable $fileServerUrlGenerator
     * @return $this
     */
    public function setFileServerUrlGenerator(callable $fileServerUrlGenerator) {
        $this->fileServerUrlGenerator = $fileServerUrlGenerator;
        return $this;
    }

}