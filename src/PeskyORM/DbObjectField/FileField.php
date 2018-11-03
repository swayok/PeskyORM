<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbFileInfo;
use PeskyORM\DbImageFileInfo;
use PeskyORM\DbObjectField;
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

    protected function init() {
        $config = $this->getConfig();
        if (empty($config->importVirtualColumnValueFrom())) {
            $config->setImportVirtualColumnValueFrom($this->getDbObject()->_getPkFieldName());
        }
    }

    public function isValidValueFormat($value, $silent = true) {
        if (!empty($value)) {
            if (!Utils::isFileUpload($value)) {
                $this->setValidationError('File upload expected', !$silent);
                return false;
            } else if (!Utils::isSuccessfullFileUpload($value)) {
                $this->setValidationError('File upload failed', !$silent);
                return false;
            } else if (!File::exist($value['tmp_name'])) {
                $this->setValidationError('File upload was successful but file is missing', !$silent);
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
        if ($this->hasUploadedFileInfo()) {
            return $this->values['value'];
        } else if ($this->isValueReceivedFromDb()) {
            return $this->getFileInfo(true, true);
        } else {
            return $orIfNoValueReturn;
        }
    }

    public function hasValue() {
        return $this->hasUploadedFileInfo() || $this->isValueReceivedFromDb();
    }

    /**
     * @return bool
     */
    public function hasUploadedFileInfo() {
        return !empty($this->values['value']) && is_array($this->values['value']);
    }

    /**
     * @return null|array
     */
    public function getUploadedFileInfo() {
        return empty($this->values['value']) ? null : $this->values['value'];
    }

    public function isValueReceivedFromDb() {
        return $this->getDbObject()->_getField($this->dbColumnConfig->importVirtualColumnValueFrom())->hasValue();
    }

    /**
     * @return bool
     * @throws DbObjectFieldException
     */
    public function hasFile() {
        $folder = Folder::load($this->getFileDirPath());
        if (!$folder->exists()) {
            return false;
        }
        $fileName = $this->getFileNameWithoutExtension();
        return count($folder->find("^{$fileName}.*")) > 0;
    }

    /**
     * @param bool $initIfNotExists
     * @param bool $readInfoFromFile
     * @return null|DbFileInfo|DbImageFileInfo
     */
    public function getFileInfo($initIfNotExists = false, $readInfoFromFile = false) {
        if ($initIfNotExists && empty($this->fileInfo)) {
            $this->fileInfo = $this->createFileInfoObject();
        }
        if ($readInfoFromFile) {
            $this->fileInfo->readFromFileOrAutodetect();
        }
        return $this->fileInfo;
    }

    /**
     * @return DbFileInfo|DbImageFileInfo
     */
    protected function createFileInfoObject() {
        $className = $this->fileInfoClassName;
        return new $className($this);
    }

    /**
     * @param array|null $value - null: means that
     * @param bool $isDbValue - not used
     * @return $this
     * @throws DbObjectFieldException
     */
    public function setValue($value, $isDbValue = false) {
        if (!empty($value) && !Utils::isFileUpload($value)) {
            throw new DbObjectFieldException($this, 'Value should be array with data about uploaded file');
        } else if (empty($value)) {
            $this->resetValue();
        } else {
            $this->values['rawValue'] = $this->values['value'] = $this->fixUploadInfo($value);
            $this->dbObject->fieldUpdated($this->getName());
        }
        $this->validate();
        return $this;
    }

    /**
     * Used to fix uploaded file info. For example: change file name or extension
     * @param array $uploadInfo
     * @return mixed
     */
    protected function fixUploadInfo($uploadInfo) {
        return $uploadInfo;
    }

    public function validate($silent = true, $forSave = false) {
        unset($this->values['error']);
        if (!$this->checkIfRequiredValueIsSet()) {
            $this->setValidationError('Field value is required', !$silent);
            return false;
        }
        if (!$this->hasUploadedFileInfo()) {
            return true; //< no upload
        }
        if (!$this->isValidValueFormat($this->getValue(), $silent)) {
            return false;
        }
        try {
            $this->detectUploadedFileExtension($this->getValue());
        } catch (DbObjectFieldException $exc) {
            $this->setValidationError($exc->getMessage(), !$silent);
            return false;
        }
        return true;
    }

    /**
     * @return bool
     * @throws \PeskyORM\Exception\DbObjectFieldException
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
        return $this->getFileDirPath() . $this->getFullFileName();
    }

    /**
     * Get absolute URL to file
     * @return string
     * @throws DbObjectFieldException
     */
    public function getAbsoluteFileUrl() {
        return $this->getFileDirAbsoluteUrl() . $this->getFullFileName();
    }

    /**
     * Get absolute FS path to file dir
     * @return string
     * @throws \PeskyORM\Exception\DbObjectException
     * @throws DbObjectFieldException
     */
    public function getFileDirPath() {
        if (!$this->getDbObject()->exists()) {
            throw new DbObjectFieldException($this, 'Unable to get file dir path of non-existing object');
        }
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
        return $dirPath;
    }

    /**
     * Get relative URL to file dir
     * @return string
     * @throws \PeskyORM\Exception\DbObjectException
     * @throws DbObjectFieldException
     */
    public function getFileDirRelativeUrl() {
        if (!$this->getDbObject()->exists()) {
            throw new DbObjectFieldException($this, 'Unable to get file url of non-existing object');
        }
        $generator = $this->dbColumnConfig->getFileDirRelativeUrlGenerator();
        if (!empty($generator)) {
            $relUrl = $generator($this);
            if (empty($relUrl) || !is_string($relUrl)) {
                throw new DbObjectFieldException($this, "File dir relative url genetartor function should return not-empty string");
            }
        } else {
            $objectSubdir = '/' . trim($this->getFilesSubdir('/'), '/\\') . '/';
            $relUrl = trim($this->getBaseUrlToFiles(), '/\\');
            if (!$this->isAbsoluteUrl($relUrl)) {
                $relUrl = '/' . $relUrl . $objectSubdir;
            } else {
                $relUrl .= $objectSubdir;
            }
        }
        return $relUrl;
    }

    /**
     * Get absolute URL to file dir
     * @return string
     * @throws \PeskyORM\Exception\DbObjectException
     * @throws DbObjectFieldException
     */
    public function getFileDirAbsoluteUrl() {
        if (!$this->getDbObject()->exists()) {
            throw new DbObjectFieldException($this, 'Unable to get file url of non-existing object');
        }
        $relativeUrl = $this->getFileDirRelativeUrl();
        if ($this->isAbsoluteUrl($relativeUrl)) {
            return $relativeUrl;
        } else {
            return $this->getFileServerUrl() . '/' . trim($relativeUrl, '/\\') . '/';
        }
    }

    public function isAbsoluteUrl($url) {
        return preg_match('%^(https?|ftp)://%is', $url);
    }

    /**
     * Get server URL where files are stored (ex: http://sub.server.com)
     * @return string
     * @throws DbObjectFieldException
     */
    public function getFileServerUrl() {
        $generator = $this->dbColumnConfig->getFileServerUrlGenerator();
        if (!empty($generator)) {
            $url = $generator($this);
            if (empty($url) || !is_string($url)) {
                throw new DbObjectFieldException($this, "File server url genetartor function should return not-empty string");
            }
        } else {
            $url = 'http://' . $_SERVER['HTTP_HOST'];
        }
        return rtrim($url, '/\\');
    }

    /**
     * Get subdir to files based on primary key and maybe some other custom things
     * @param string $directorySeparator - directory separator
     * @return string
     * @throws \PeskyORM\Exception\DbObjectException
     * @throws \PeskyORM\Exception\DbColumnConfigException
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
            return $this->getDbObject()->_getFieldValue($this->getConfig()->importVirtualColumnValueFrom());
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
     * @throws \PeskyORM\Exception\DbColumnConfigException
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
     * @throws \PeskyORM\Exception\DbObjectFieldException
     */
    public function getFullFileName() {
        $fileInfo = $this->getFileInfo(true, true);
        if (!empty($fileInfo->getFileNameWithExtension())) {
            return $fileInfo->getFileNameWithExtension();
        } else {
            $fileName = $this->getFileNameWithoutExtension();
            $fileInfo->setFileNameWithoutExtension($fileName);
            $ext = $this->getFileExtension();
            if (!empty($ext)) {
                $fileName .= '.' . $ext;
            }
            $fileInfo->setFileNameWithExtension($fileName);
            return $fileName;
        }
    }

    /**
     * Get original file name with extension
     * @return string
     * @throws \PeskyORM\Exception\DbObjectFieldException
     */
    public function getOriginalFullFileName() {
        $fileInfo = $this->getFileInfo(true, true);
        if (!empty($fileInfo->getOriginalFileNameWithExtension())) {
            return $fileInfo->getOriginalFileNameWithExtension();
        } else {
            return $this->getFullFileName();
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
    public function detectUploadedFileExtension(array $uploadedFileInfo) {
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
        $receivedExt = strtolower($receivedExt);
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
     * @throws \PeskyORM\Exception\DbObjectException
     * @throws DbObjectFieldException
     */
    public function getFileExtension() {
        $fileInfo = $this->getFileInfo(true, true);
        if (empty($fileInfo->getFileExtension())) {
            $fileInfo->setFileExtension($this->getDefaultFileExtension());
            if ($this->getDbObject()->exists()) {
                $allowedExtensions = $this->getAllowedFileExtensions();
                if (!empty($allowedExtensions)) {
                    foreach ($allowedExtensions as $ext) {
                        $fileDir = $this->getFileDirPath();
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

    /**
     * @return null|string|bool - null: not an upload | string: FS path fo file | false: invalid file uploaded (validation error)
     * @throws \PeskyORM\Exception\DbObjectException
     * @throws DbObjectFieldException
     */
    public function saveUploadedFile() {
        if (!$this->getDbObject()->exists(true)) {
            throw new DbObjectFieldException($this, 'Unable to save file of non-existing object');
        }
        $uploadedFileInfo = $this->getUploadedFileInfo();
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
     * @throws \PeskyORM\Exception\DbObjectFieldException
     */
    protected function analyzeUploadedFileAndSaveToFS(array $uploadedFileInfo) {
        $pathToFiles = $this->getFileDirPath();
        if (!is_dir($pathToFiles)) {
            Folder::add($pathToFiles, 0777);
        }
        $fileInfo = $this->createFileInfoObject();

        $fileInfo->setOriginalFileNameWithoutExtension(preg_replace('%\.[a-zA-Z0-9]{1,6}$%', '', $uploadedFileInfo['name']));
        $fileInfo->setOriginalFileNameWithExtension($uploadedFileInfo['name']);

        $fileName = $this->getFileNameWithoutExtension();
        $fileInfo->setFileNameWithoutExtension($fileName);
        $fileInfo->setFileNameWithExtension($fileName);
        try {
            $ext = $this->detectUploadedFileExtension($uploadedFileInfo);
            if ($ext === false) {
                $this->setValidationError('Unable to detect uploaded file extension and content type');
                return false;
            } else if (!empty($ext)) {
                $fileName .= '.' . $ext;
                $fileInfo->setFileExtension($ext);
                $fileInfo->setFileNameWithExtension($fileName);
                $fileInfo->setOriginalFileNameWithExtension($fileInfo->getOriginalFileNameWithoutExtension() . '.' . $ext);
            }
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
     * @param DbFileInfo $fileInfo
     * @return bool
     */
    protected function storeFileToFS($uploadedFileInfo, $pathToFiles, $fileInfo) {
        $filePath = $pathToFiles . $fileInfo->getFileNameWithExtension();
        return File::load($uploadedFileInfo['tmp_name'])->move($filePath, 0666) ? $fileInfo : false;
    }

    /**
     * Save $fileInfo to file and to $this->fileInfo
     * @param array|DbFileInfo|DbImageFileInfo $fileInfo
     * @return $this
     * @throws DbObjectFieldException
     */
    protected function saveUploadedFileInfo($fileInfo) {
        if (is_array($fileInfo)) {
            $this->fileInfo = $this->createFileInfoObject()->update($fileInfo);
            $this->fileInfo->saveToFile();
            return $this;
        } else if ($fileInfo instanceof DbFileInfo) {
            $this->fileInfo = $fileInfo;
            $fileInfo->saveToFile();
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