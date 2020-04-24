<?php

namespace PeskyORM\DbColumnConfig;

use PeskyORM\DbColumnConfig;

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
        return new static($name, $basePathToFiles, $baseUrlToFiles);
    }

    /**
     * @param string $name
     * @param string|\Closure $basePathToFiles
     * @param string|null|\Closure $baseUrlToFiles
     */
    public function __construct($name, $basePathToFiles, $baseUrlToFiles = null) {
        parent::__construct($name, $this->_type);
        if (!empty($basePathToFiles)) {
            $this->setBasePathToFiles($basePathToFiles);
        }
        if (!empty($baseUrlToFiles)) {
            $this->setBaseUrlToFiles($baseUrlToFiles);
        }
    }

    /**
     * @return string
     */
    public function getBasePathToFiles() {
        return is_callable($this->basePathToFiles) ? call_user_func($this->basePathToFiles) : $this->basePathToFiles;
    }

    /**
     * @param string|\Closure $basePathToFiles
     * @return $this
     */
    public function setBasePathToFiles($basePathToFiles) {
        if (empty($basePathToFiles) || (!is_string($basePathToFiles) && !($basePathToFiles instanceof \Closure))) {
            throw new \InvalidArgumentException('$basePathToFiles argument must be a not-empty string or \Closure');
        }
        $this->basePathToFiles = $basePathToFiles;
        return $this;
    }

    /**
     * @return string
     */
    public function getBaseUrlToFiles(): string {
        if ($this->baseUrlToFiles === null) {
            throw new \UnexpectedValueException('$this->baseUrlToFiles is not provided');
        }
        return is_callable($this->baseUrlToFiles) ? call_user_func($this->baseUrlToFiles) : $this->baseUrlToFiles;
    }

    /**
     * @param string|null|\Closure $baseUrlToFiles
     * @return $this
     */
    public function setBaseUrlToFiles($baseUrlToFiles) {
        if (!is_string($baseUrlToFiles) && !($baseUrlToFiles instanceof \Closure)) {
            throw new \InvalidArgumentException('$baseUrlToFiles argument must be a string or \Closure');
        }
        if (is_string($baseUrlToFiles) && preg_match('%(https?://[^/]+)(/.*$|$)%i', $baseUrlToFiles, $urlParts)) {
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
    public function getDefaultFileExtension(): ?string {
        return $this->defaultFileExtension;
    }

    /**
     * @param string $defaultFileExtension
     * @return $this
     */
    public function setDefaultFileExtension(string $defaultFileExtension) {
        if (empty($defaultFileExtension)) {
            throw new \InvalidArgumentException('$defaultFileExtension argument must be a not-empty string');
        }
        $allowedExtensions = $this->getAllowedFileExtensions();
        if (!empty($allowedExtensions) && !in_array($defaultFileExtension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException("\$defaultFileExtension argument value '{$defaultFileExtension}' is not allowed");
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
     */
    public function setAllowedFileExtensions(array $allowedFileExtensions) {
        if (count($allowedFileExtensions) === 0) {
            throw new \InvalidArgumentException('$allowedFileExtensions argument must be a not-empty array');
        }
        $defaultExtension = $this->getDefaultFileExtension();
        if (!empty($defaultExtension) && !in_array($defaultExtension, $allowedFileExtensions, true)) {
            throw new \InvalidArgumentException("Default file extension '$defaultExtension' provided via setDefaultFileExtension() is not allowed");
        }
        $this->allowedFileExtensions = array_values($allowedFileExtensions);
        return $this;
    }

    public function getFileDirRelativeUrlGenerator(): ?\Closure {
        return $this->fileDirRelativeUrlGenerator;
    }

    /**
     * @param \Closure $fileDirRelativeUrlGenerator - function (FileField $field) {}
     * @return $this
     */
    public function setFileDirRelativeUrlGenerator(\Closure $fileDirRelativeUrlGenerator) {
        $this->fileDirRelativeUrlGenerator = $fileDirRelativeUrlGenerator;
        return $this;
    }

    public function getFileDirPathGenerator(): ?\Closure {
        return $this->fileDirPathGenerator;
    }

    /**
     * @param \Closure $fileDirPathGenerator - function (FileField $field) {}
     * @return $this
     */
    public function setFileDirPathGenerator(\Closure $fileDirPathGenerator) {
        $this->fileDirPathGenerator = $fileDirPathGenerator;
        return $this;
    }

    public function getFileSubdirGenerator(): ?\Closure {
        return $this->fileSubdirGenerator;
    }

    /**
     * @param \Closure $fileSubdirGenerator - function (FileField $field, $directorySeparator = DIRECTORY_SEPARATOR) {}
     * @return $this
     */
    public function setFileSubdirGenerator(\Closure $fileSubdirGenerator) {
        $this->fileSubdirGenerator = $fileSubdirGenerator;
        return $this;
    }

    public function getFileNameGenerator(): ?\Closure {
        return $this->fileNameGenerator;
    }

    /**
     * @param \Closure $fileNameGenerator - function (FileField $field) {}
     * @return $this
     */
    public function setFileNameGenerator(\Closure $fileNameGenerator) {
        $this->fileNameGenerator = $fileNameGenerator;
        return $this;
    }

    public function getFileServerUrlGenerator(): ?\Closure {
        return $this->fileServerUrlGenerator;
    }

    /**
     * @return bool
     */
    public function hasFileServerUrlGenerator() {
        return (bool)$this->fileServerUrlGenerator;
    }

    /**
     * @param \Closure $fileServerUrlGenerator
     * @return $this
     */
    public function setFileServerUrlGenerator(\Closure $fileServerUrlGenerator) {
        $this->fileServerUrlGenerator = $fileServerUrlGenerator;
        return $this;
    }

}