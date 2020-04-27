<?php

namespace PeskyORM\DbColumnConfig;

use PeskyORM\DbColumnConfig;
use PeskyORM\DbFileInfo;
use PeskyORM\DbImageFileInfo;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\RecordValue;
use Swayok\Utils\File;
use Swayok\Utils\Folder;
use Swayok\Utils\Utils;

class FileColumnConfig extends DbColumnConfig {
    
    static public $infoFileName = 'info.json';
    
    protected $_type = self::TYPE_FILE;
    
    protected $fileInfoClassName = DbFileInfo::class;

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
     * @var null|\Closure
     */
    protected $fileNameGenerator = null;
    /**
     * @var null|\Closure
     */
    protected $fileSubdirGenerator = null;
    /**
     * @var null|\Closure
     */
    protected $fileDirPathGenerator = null;
    /**
     * @var null|\Closure
     */
    protected $fileDirRelativeUrlGenerator = null;
    /**
     * @var null|\Closure
     */
    protected $fileServerUrlGenerator = null;

    /**
     * @param string $name
     * @param string|null $basePathToFiles
     * @param string|null $baseUrlToFiles
     * @return $this
     */
    static public function create($basePathToFiles = null, $baseUrlToFiles = null, $name = null) {
        return new static($name, $basePathToFiles, $baseUrlToFiles);
    }

    /**
     * @param string $name
     * @param string|\Closure $basePathToFiles
     * @param string|null|\Closure $baseUrlToFiles
     */
    public function __construct($name, $basePathToFiles, $baseUrlToFiles = null) {
        parent::__construct($name, $this->_type);
        $this->doesNotExistInDb();
        if (!empty($basePathToFiles)) {
            $this->setBasePathToFiles($basePathToFiles);
        }
        if (!empty($baseUrlToFiles)) {
            $this->setBaseUrlToFiles($baseUrlToFiles);
        }
        $this->configureColumnClosures();
    }
    
    protected function configureColumnClosures() {
        $this->setValueGetter(function (RecordValue $value, $format = null) {
            $record = $value->getRecord();
            if ($record->existsInDb()) {
                return $this->getFileInfo($value);
            } else {
                return $value->getValue();
            }
        });
        $this->setValueValidator(function ($value, $isFromDb, $isForCondition) {
            if (!$isFromDb && !empty($value)) {
                if (!Utils::isFileUpload($value)) {
                    return ['File upload expected'];
                } else if (!Utils::isSuccessfullFileUpload($value)) {
                    return ['File upload failed'];
                } else if (!File::exist($value['tmp_name'])) {
                    return ['File upload was successful but file is missing'];
                }
            }
            return [];
        });
        $this->setValueSetter(function ($newValue, $isFromDb, RecordValue $valueContainer, $trustDataReceivedFromDb) {
            if ($isFromDb || empty($newValue)) {
                return;
            }
            // todo: validate and save file
        });
    }
    
    /**
     * Get absolute FS path to file dir
     */
    public function getFileDirPath(Record $record): string {
        $this->requireRecordExistence($record);
        $generator = $this->getFileDirPathGenerator();
        if (!empty($generator)) {
            $dirPath = $generator($this, $record);
            if (empty($dirPath) || !is_string($dirPath)) {
                throw new \UnexpectedValueException('File dir path genetartor function should return not-empty string');
            }
        } else {
            $objectSubdir = DIRECTORY_SEPARATOR . trim($this->getFilesSubdir($record), '/\\');
            $dirPath = rtrim($this->getBasePathToFiles(), '/\\') . $objectSubdir . DIRECTORY_SEPARATOR;
        }
        return $dirPath;
    }
    
    protected function getFilesSubdir(Record $record, $directorySeparator = DIRECTORY_SEPARATOR): string {
        $this->requireRecordExistence($record);
        $generator = $this->getFileSubdirGenerator();
        if (!empty($generator)) {
            $subdir = $generator($this, $record, $directorySeparator);
            if (empty($subdir) || !is_string($subdir)) {
                throw new \UnexpectedValueException('File subdir genetartor function should return not-empty string');
            }
            return $subdir;
        } else {
            return $record->getPrimaryKeyValue();
        }
    }
    
    public function getInfoFilePath(Record $record): string {
        return $this->getFileDirPath($record) . $this->getName() . '_' . self::$infoFileName;
    }
    
    public function getFilePath(RecordValue $valueContainer): string {
        return $this->getFileDirPath($valueContainer->getRecord()) . $this->getFullFileName($valueContainer);
    }
    
    protected function getFullFileName(RecordValue $valueContainer) {
        $fileInfo = $this->getFileInfo($valueContainer);
        if (!empty($fileInfo->getFileNameWithExtension())) {
            return $fileInfo->getFileNameWithExtension();
        } else {
            $fileName = $this->getFileNameWithoutExtension();
            $fileInfo->setFileNameWithoutExtension($fileName);
            $ext = $this->getFileExtension($valueContainer);
            if (!empty($ext)) {
                $fileName .= '.' . $ext;
            }
            $fileInfo->setFileNameWithExtension($fileName);
            return $fileName;
        }
    }
    
    /**
     * @param RecordValue $valueContainer
     * @return DbFileInfo|DbImageFileInfo
     */
    public function getFileInfo(RecordValue $valueContainer) {
        return $valueContainer->getCustomInfo(
            'FileInfo',
            function () use ($valueContainer) {
                $class = $this->fileInfoClassName;
                return new $class($valueContainer);
            }, true
        );
    }
    
    /**
     * Get file name without extension
     * @param string|null|\Closure $fallbackValue - null: $this->getName() is used
     * @return string - file name without extension
     */
    public function getFileNameWithoutExtension($fallbackValue = null): string {
        $generator = $this->getFileNameGenerator();
        if (!empty($generator)) {
            $fileName = $generator($this);
            if (empty($fileName) || !is_string($fileName)) {
                throw new \UnexpectedValueException('File name genetartor function should return not-empty string');
            }
            return $fileName;
        } else {
            return empty($fallbackValue) ? $this->getName() : $fallbackValue;
        }
    }
    
    protected function getFileExtension(RecordValue $valueContainer): string {
        $fileInfo = $this->getFileInfo($valueContainer);
        if (empty($fileInfo->getFileExtension())) {
            $fileInfo->setFileExtension($this->getDefaultFileExtension());
            if ($valueContainer->getRecord()->existsInDb()) {
                $allowedExtensions = $this->getAllowedFileExtensions();
                if (!empty($allowedExtensions)) {
                    foreach ($allowedExtensions as $ext) {
                        $fileDir = $this->getFileDirPath($valueContainer->getRecord());
                        $fileNameNoExt = $this->getFileNameWithoutExtension();
                        if (File::exist($fileDir . $fileNameNoExt . '.' . $ext)) {
                            $fileInfo->setFileExtension($ext);
                            return $ext;
                        }
                    }
                }
            }
        }
        return $fileInfo->getFileExtension();
    }
    
    public function getAbsoluteFileUrl(RecordValue $valueContainer) {
        return $this->getFileDirAbsoluteUrl($valueContainer->getRecord()) . $this->getFullFileName($valueContainer);
    }
    
    protected function getFileDirAbsoluteUrl(Record $record): string {
        $this->requireRecordExistence($record);
        $relativeUrl = $this->getFileDirRelativeUrl($record);
        if ($this->isAbsoluteUrl($relativeUrl)) {
            return $relativeUrl;
        } else {
            return $this->getFileServerUrl() . '/' . trim($relativeUrl, '/\\') . '/';
        }
    }
    
    protected function getFileDirRelativeUrl(Record $record): string {
        $this->requireRecordExistence($record);
        $generator = $this->getFileDirRelativeUrlGenerator();
        if (!empty($generator)) {
            $relUrl = $generator($this, $record);
            if (empty($relUrl) || !is_string($relUrl)) {
                throw new \UnexpectedValueException('File dir relative url genetartor function should return not-empty string');
            }
        } else {
            $objectSubdir = '/' . trim($this->getFilesSubdir($record, '/'), '/\\') . '/';
            $relUrl = trim($this->getBaseUrlToFiles(), '/\\');
            if (!$this->isAbsoluteUrl($relUrl)) {
                $relUrl = '/' . $relUrl . $objectSubdir;
            } else {
                $relUrl .= $objectSubdir;
            }
        }
        return $relUrl;
    }
    
    protected function getFileServerUrl(): string {
        $generator = $this->getFileServerUrlGenerator();
        if (!empty($generator)) {
            $url = $generator($this);
            if (empty($url) || !is_string($url)) {
                throw new \UnexpectedValueException('File server url genetartor function should return not-empty string');
            }
        } else {
            $url = 'http://' . $_SERVER['HTTP_HOST'];
        }
        return rtrim($url, '/\\');
    }
    
    protected function isAbsoluteUrl(string $url): bool {
        return preg_match('%^(https?|ftp)://%i', $url);
    }
    
    public function isFileExists(RecordValue $valueContainer) {
        return File::exist($this->getFilePath($valueContainer));
    }
    
    protected function requireRecordExistence(Record $record) {
        if (!$record->existsInDb()) {
            throw new \BadMethodCallException('Unable to get file dir path of non-existing db record');
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
     * @param \Closure $fileDirRelativeUrlGenerator - function (FileColumnConfig $column, Record $record) {}
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
     * @param \Closure $fileDirPathGenerator - function (FileColumnConfig $column, Record $record) {}
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
     * @param \Closure $fileSubdirGenerator - function (FileColumnConfig $column, Record $record, $directorySeparator = DIRECTORY_SEPARATOR) {}
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
     * @param \Closure $fileNameGenerator - function (FileColumnConfig $column) {}
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
    
    /**
     * Get original file name with extension
     * @return string
     */
    protected function getOriginalFullFileName(RecordValue $recordValue) {
        $fileInfo = $this->getFileInfo($recordValue);
        if ($fileInfo->hasOriginalFileNameWithExtension()) {
            return $fileInfo->getOriginalFileNameWithExtension();
        } else {
            return $this->getFullFileName($recordValue);
        }
    }
    
    public function deleteFiles(Record $record) {
        if (!$record->existsInDb()) {
            throw new \InvalidArgumentException('Unable to delete files of non-existing object');
        }
        $pathToFiles = $this->getFileDirPath($record);
        if (Folder::exist($pathToFiles)) {
            $baseFileName = $this->getFileNameWithoutExtension();
            $files = Folder::load($pathToFiles)->find("{$baseFileName}.*");
            foreach ($files as $fileName) {
                File::remove($pathToFiles . $fileName);
            }
        }
    }

}