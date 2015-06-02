<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbFileInfo;
use PeskyORM\DbImageFileInfo;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbColumnConfigException;
use PeskyORM\Exception\DbExceptionCode;
use PeskyORM\Exception\DbObjectFieldException;
use PeskyORM\Exception\DbObjectException;
use Swayok\Utils\File;
use Swayok\Utils\Folder;
use Swayok\Utils\Utils;

class FileField extends DbObjectField {

    static public $contentTypeToExt = array(
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
    );

    static public $infoFileName = 'info.json';

    /**
     * @var string
     */
    protected $fileInfoClassName = 'PeskyORM\DbFileInfo';
    /**
     * @var DbFileInfo|DbImageFileInfo|null
     */
    protected $fileInfo = null;

    public function isValidValueFormat($value) {
        if (!empty($value)) {
            if (!Utils::isFileUpload($value)) {
                $this->setValidationError('File upload expected');
                return false;
            } else if (!Utils::isSuccessfullFileUpload($value)) {
                $this->setValidationError('File upload failed');
                return false;
            } else if (!File::exist($value['tmp_name'])) {
                $this->setValidationError('File upload was successful but file is missing');
                return false;
            }
        }
        return true;
    }

    public function resetValue() {
        $this->fileInfo = null;
        return parent::resetValue();
    }

    /**
     * @param null $orIfNoValueReturn
     * @return DbFileInfo|DbImageFileInfo|array|null
     * @throws DbObjectFieldException
     */
    public function getValue($orIfNoValueReturn = null) {
        return $this->hasValue() ? $this->values['value'] : $orIfNoValueReturn;
    }

    public function isValueReceivedFromDb() {
        return !$this->hasValue();
    }

    /**
     * @return bool
     * @throws DbObjectFieldException
     */
    public function hasFile() {
        if (!array_key_exists('fileExists', $this->values)) {
            $folder = Folder::load($this->getFileDirPath());
            if (!$folder->exists()) {
                return false;
            }
            $fileName = $this->getFileNameWithoutExtension();
            $this->values['fileExists'] = count($folder->find("^{$fileName}.*")) > 0;
        }
        return $this->values['fileExists'];
    }

    /**
     * @param bool $initIfNotExists
     * @param bool $readInfoFromFile
     * @return null|DbFileInfo|DbImageFileInfo
     */
    public function getFileInfo($initIfNotExists = false, $readInfoFromFile = false) {
        if ($initIfNotExists && empty($this->fileInfo)) {
            $className = $this->fileInfoClassName;
            $this->fileInfo = new $className($this);
        }
        if ($readInfoFromFile) {
            $this->fileInfo->readFromFileOrAutodetect();
        }
        return $this->fileInfo;
    }

    /**
     * @param array|null $value - null: means that
     * @param bool $isDbValue - not used
     * @return $this
     * @throws DbObjectFieldException
     */
    public function setValue($value, $isDbValue = false) {
        if (!Utils::isFileUpload($value)) {
            throw new DbObjectFieldException($this, 'Value should be array with data about uploaded file');
        }
        $this->values['rawValue'] = $this->values['value'] = $value;
        $this->validate();
        return $this;
    }

    public function validate($silent = true, $forSave = false) {
        unset($this->values['error']);
        if (!$this->checkIfRequiredValueIsSet()) {
            $this->setValidationError('Field value is required');
        } else if (!$this->hasValue()) {
            return true; //< no upload
        } else if (!$this->isValidValueFormat($this->getValue())) {
        } else {
            try {
                $this->detectUploadedFileExtension($this->getValue());
            } catch (DbColumnConfigException $exc) {
                $this->setValidationError($exc->getMessage());
                return false;
            }
        }
        if (!$silent && !$this->isValid()) {
            throw new DbObjectFieldException($this, $this->getValidationError());
        }
        return true;
    }

    /**
     * @return bool
     */
    public function isFileExists() {
        return File::exist($this->getFilePath());
    }

    /**
     * @return string
     * @throws DbObjectFieldException
     */
    public function getInfoFilePath() {
        return $this->getFileDirPath() . $this->getName() . '_' . self::$infoFileName;
    }

    /**
     * Get absolute FS path to file
     * @return string
     * @throws DbObjectFieldException
     */
    public function getFilePath() {
        if (empty($this->values['file_path'])) {
            $this->values['file_path'] = $this->getFileDirPath() . $this->getFullFileName();
        }
        return $this->values['file_path'];
    }

    /**
     * Get absolute URL to file
     * @return string
     * @throws DbObjectFieldException
     */
    public function getFileUrl() {
        if (empty($this->values['file_url'])) {
            $this->values['file_url'] = $this->getFileDirAbsoluteUrl() . $this->getFullFileName();
        }
        return $this->values['file_url'];
    }

    /**
     * Get absolute FS path to file dir
     * @return string
     * @throws DbObjectFieldException
     */
    public function getFileDirPath() {
        if (!$this->getDbObject()->exists()) {
            throw new DbObjectFieldException($this, 'Unable to get file dir path of non-existing object');
        }
        if (empty($this->values['file_dir_path'])) {
            $generator = $this->dbColumnConfig->getFileDirPathGenerator();
            if (!empty($generator)) {
                $dirPath = $generator($this);
                if (empty($dirPath) || !is_string($dirPath)) {
                    throw new DbObjectFieldException($this, "File dir path genetartor function should return not-empty string");
                }
            } else {
                $objectSubdir = DIRECTORY_SEPARATOR . trim($this->getFilesSubdir(DIRECTORY_SEPARATOR), '/\\');
                $dirPath = rtrim($this->getBasePathToFiles(), '/\\') . $objectSubdir . DIRECTORY_SEPARATOR;
            }
            $this->values['file_dir_path'] = $dirPath;
        }
        return $this->values['file_dir_path'];
    }

    /**
     * Get relative URL to file dir
     * @return string
     * @throws DbObjectFieldException
     */
    public function getFileDirRelativeUrl() {
        if (!$this->getDbObject()->exists()) {
            throw new DbObjectFieldException($this, 'Unable to get file url of non-existing object');
        }
        if (empty($this->values['file_dir_relative_url'])) {
            $generator = $this->dbColumnConfig->getFileDirRelativeUrlGenerator();
            if (!empty($generator)) {
                $relUrl = $generator($this);
                if (empty($relUrl) || !is_string($relUrl)) {
                    throw new DbObjectFieldException($this, "File dir relative url genetartor function should return not-empty string");
                }
            } else {
                $objectSubdir = '/' . trim($this->getFilesSubdir('/'), '/\\');;
                $relUrl = '/' . trim($this->getBaseUrlToFiles(), '/\\') . $objectSubdir . '/';
            }
            $this->values['file_dir_relative_url'] = $relUrl;
        }
        return $this->values['file_dir_relative_url'];
    }

    /**
     * Get absolute URL to file dir
     * @return string
     * @throws DbObjectFieldException
     */
    public function getFileDirAbsoluteUrl() {
        if (!$this->getDbObject()->exists()) {
            throw new DbObjectFieldException($this, 'Unable to get file url of non-existing object');
        }
        if (empty($this->values['file_dir_absolute_url'])) {
            $this->values['file_dir_absolute_url'] = rtrim($this->getFileServerUrl(), '/\\') . '/' . trim($this->getFileDirRelativeUrl(), '/\\') . '/';
        }
        return $this->values['file_dir_absolute_url'];
    }

    /**
     * Get server URL where files are stored (ex: http://sub.server.com)
     * @return string
     * @throws DbObjectFieldException
     */
    public function getFileServerUrl() {
        if (empty($this->values['server_url'])) {
            $generator = $this->dbColumnConfig->getFileServerUrlGenerator();
            if (!empty($generator)) {
                $url = $generator($this);
                if (empty($url) || !is_string($url)) {
                    throw new DbObjectFieldException($this, "File server url genetartor function should return not-empty string");
                }
            } else {
                $url = 'http://' . $_SERVER['HTTP_HOST'];
            }
            $this->values['server_url'] = $url;
        }
        return $this->values['server_url'];
    }

    /**
     * Get subdir to files based on primary key and maybe some other custom things
     * @param string $directorySeparator - directory separator
     * @return string
     * @throws DbObjectFieldException
     */
    public function getFilesSubdir($directorySeparator = DIRECTORY_SEPARATOR) {
        if (!$this->getDbObject()->exists()) {
            throw new DbObjectFieldException($this, 'Unable to get file subdir of non-existing object');
        }
        $generator = $this->dbColumnConfig->getFileSubdirGenerator();
        if (!empty($generator)) {
            $subdir = $generator($this, $directorySeparator);
            if (empty($subdir) || !is_string($subdir)) {
                throw new DbObjectFieldException($this, "File subdir genetartor function should return not-empty string");
            }
            return $subdir;
        } else {
            return $this->getDbObject()->_getPkValue();
        }
    }

    /**
     * @return string
     */
    public function getBasePathToFiles() {
        return $this->dbColumnConfig->getBasePathToFiles();
    }

    /**
     * @return string
     */
    public function getBaseUrlToFiles() {
        return $this->dbColumnConfig->getBaseUrlToFiles();
    }

    /**
     * Get file name without extension
     * @param string|callable|null $fallbackValue - null: $this->getName() is used
     * @return string - file name without extension
     * @throws DbObjectFieldException
     */
    public function getFileNameWithoutExtension($fallbackValue = null) {
        $generator = $this->dbColumnConfig->getFileNameGenerator();
        if (!empty($generator)) {
            $fileName = $generator($this);
            if (empty($fileName) || !is_string($fileName)) {
                throw new DbObjectFieldException($this, "File name genetartor function should return not-empty string");
            }
            return $fileName;
        } else {
            return empty($fallbackValue) ? $this->getName() : $fallbackValue;
        }
    }

    /**
     * Get file name with extension
     * @return string
     */
    public function getFullFileName() {
        $fileInfo = $this->getFileInfo();
        if (!empty($fileInfo)) {
            return $fileInfo->getFileNameWithExtension();
        } else {
            $fileName = $this->getFileNameWithoutExtension();
            $ext = $this->getFileExtension();
            if (!empty($ext)) {
                $fileName .= '.' . $ext;
            }
            return $fileName;
        }
    }

    /**
     * @return string
     */
    public function getDefaultFileExtension() {
        $ext = $this->dbColumnConfig->getDefaultFileExtension();
        if (empty($ext)) {
            $allowedExtensions = $this->getAllowedFileExtensions();
            $ext = empty($allowedExtensions) ? '' : $allowedExtensions[0];
        }
        return $ext;
    }

    /**
     * @return array|null
     */
    public function getAllowedFileExtensions() {
        return $this->dbColumnConfig->getAllowedFileExtensions();
    }

    /**
     * @param string $ext
     * @return bool
     */
    public function isFileExtensionAllowed($ext) {
        $expectedExts = $this->getAllowedFileExtensions();
        return is_string($ext) && (empty($expectedExts) || in_array($ext, $expectedExts));
    }

    /**
     * Detect Uploaded file extension by file name or content type
     * @param array $uploadedFileInfo - uploaded file info
     * @return string - file extension without leading point (ex: 'mp4', 'mov', '')
     * @throws DbObjectFieldException
     */
    public function detectUploadedFileExtension($uploadedFileInfo) {
        if (empty($uploadedFileInfo['type']) && empty($uploadedFileInfo['name']) && empty($uploadedFileInfo['tmp_name'])) {
            throw new DbObjectFieldException(
                $this,
                'Uploaded file extension cannot be detected',
                DbExceptionCode::FILE_EXTENSION_DETECTION_FAILED
            );
        }
        // test content type
        if (!empty($uploadedFileInfo['type']) && isset(self::$contentTypeToExt[$uploadedFileInfo['type']])) {
            $receivedExt = self::$contentTypeToExt[$uploadedFileInfo['type']];
        } else if (!empty($uploadedFileInfo['name'])) {
            $receivedExt = preg_match('%\.([a-zA-Z0-9]+)\s*$%is', $uploadedFileInfo['name'], $matches) ? $matches[1] : '';
        } else if (!empty($uploadedFileInfo['tmp_name'])) {
            $receivedExt = preg_match('%\.([a-zA-Z0-9]+)\s*$%is', $uploadedFileInfo['tmp_name'], $matches) ? $matches[1] : '';
        } else {
            $receivedExt = $this->getDefaultFileExtension();
        }
        if (!$this->isFileExtensionAllowed($receivedExt)) {
            throw new DbObjectFieldException(
                $this,
                'Uploaded file extension is not allowed',
                DbExceptionCode::FILE_EXTENSION_NOT_ALLOWED
            );
        }
        return $receivedExt;
    }

    /**
     * @return string
     * @throws DbObjectFieldException
     */
    public function getFileExtension() {
        $fileInfo = $this->getFileInfo();
        if (!empty($fileInfo) && !empty($fileInfo->getFileExtension())) {
            return $fileInfo->getFileExtension();
        } else {
            if ($this->getDbObject()->exists()) {
                $allowedExtensions = $this->getAllowedFileExtensions();
                if (!empty($allowedExtensions)) {
                    foreach ($allowedExtensions as $ext) {
                        $fileDir = $this->getFileDirPath();
                        $fileNameNoExt = $this->getFileNameWithoutExtension();
                        if (File::exist($fileDir . $fileNameNoExt . '.' . $ext)) {
                            if (!empty($fileInfo)) {
                                $fileInfo->setFileExtension($ext);
                            }
                            return $ext;
                        }
                    }
                }
            }
            return $this->getDefaultFileExtension();
        }
    }

    /**
     * @return null|string|bool - null: not an upload | string: FS path fo file | false: invalid file uploaded (validation error)
     * @throws DbObjectFieldException
     */
    public function saveUploadedFile() {
        if (!$this->getDbObject()->exists(true)) {
            throw new DbObjectFieldException($this, 'Unable to save file of non-existing object');
        }
        $uploadedFileInfo = $this->getValue();
        if (empty($uploadedFileInfo)) {
            return null;
        }
        if (!$this->validate(true, true)) {
            return false;
        }
        if (!defined('UNLIMITED_EXECUTION') || !UNLIMITED_EXECUTION) {
            set_time_limit(90);
            ini_set('memory_limit', '128M');
        }
        $fileInfo = $this->analyzeUploadedFileAndSaveToFS($uploadedFileInfo);
        if (!empty($fileInfo)) {
            $this->saveUploadedFileInfo($fileInfo);
            parent::resetValue(); //< this will not remove $this->fileInfo
            return $this->getFileInfo();
        }
        return false;
    }

    /**
     * Save file to FS + collect information
     * @param array $uploadedFileInfo - uploaded file info
     * @return bool|array - array: information about file same as when you get by callings $this->getFileInfoFromInfoFile()
     */
    protected function analyzeUploadedFileAndSaveToFS($uploadedFileInfo) {
        $pathToFiles = $this->getFileDirPath();
        if (!is_dir($pathToFiles)) {
            Folder::add($pathToFiles, 0777);
        }
        $fileName = $this->getFileNameWithoutExtension();
        $fileInfo = array(
            'file_name' => $fileName,
            'full_file_name' => $fileName,
        );
        try {
            $ext = $this->detectUploadedFileExtension($uploadedFileInfo);
            if ($ext === false) {
                $this->setValidationError('Unable to detect uploaded file extension and content type');
                return false;
            } else if (!empty($ext)) {
                $fileName .= '.' . $ext;
                $fileInfo['full_file_name'] = $fileName;
            }
            $fileInfo['ext'] = $ext;

            // note: ext could be an empty string
        } catch (DbObjectFieldException $exc) {
            $this->setValidationError($exc->getMessage());
            return false;
        }

        // move tmp file to target file path
        return $this->storeFileToFS($uploadedFileInfo, $pathToFiles, $fileInfo);
    }

    /**
     * Store file to FS
     * @param array $uploadedFileInfo
     * @param string $pathToFiles
     * @param array $fileInfo
     * @return bool
     */
    protected function storeFileToFS($uploadedFileInfo, $pathToFiles, $fileInfo) {
        $filePath = $pathToFiles . $fileInfo['full_file_name'];
        return File::load($uploadedFileInfo['tmp_name'])->move($filePath, 0666) ? $fileInfo : false;
    }

    /**
     * Save $fileInfo to file and to $this->values['file_info']
     * @param array $fileInfo
     * @return $this
     * @throws DbObjectFieldException
     */
    protected function saveUploadedFileInfo($fileInfo) {
        if (is_array($fileInfo)) {
            $this->fileInfo = null;
            $this->getFileInfo(true)
                ->update($fileInfo)
                ->saveToFile();
            return $this;
        } else {
            throw new DbObjectFieldException($this, '$fileInfo shoud be an array');
        }
    }

    /**
     * Delete files attached to DbObject field
     * @throws DbObjectException
     */
    public function deleteFiles() {
        if (!$this->getDbObject()->exists()) {
            throw new DbObjectFieldException($this, 'Unable to delete files of non-existing object');
        }
        $pathToFiles = $this->getFileDirPath();
        if (Folder::exist($pathToFiles)) {
            $baseFileName = $this->getFileNameWithoutExtension();
            $files = Folder::load($pathToFiles)->find("{$baseFileName}.*");
            foreach ($files as $fileName) {
                File::remove($pathToFiles . $fileName);
            }
        }
    }
}