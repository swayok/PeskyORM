<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbColumnConfigException;
use PeskyORM\Exception\DbExceptionCode;
use PeskyORM\Exception\DbFieldException;
use PeskyORM\Exception\DbObjectException;
use PeskyORM\Lib\File;
use PeskyORM\Lib\Folder;
use PeskyORM\Lib\Utils;

class FileField extends DbObjectField {

    static public $extToConetntType = array(
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
    );

    static public $infoFileName = 'info.json';

    public function isValidValueFormat($value) {
        if (empty($value) || is_string($value) || Utils::isUploadedFile($value)) {
            return true;
        }
        $this->setValidationError('File upload expected');
        return false;
    }

    protected function doBasicValueValidationAndConvertion($value) {
        return $this->formatFile($value);
    }

    /**
     * Format file info
     * @param $value
     * @return array - if image uploaded - image inf, else - urls to image versions
     */
    protected function formatFile($value) {
        if (!is_array($value) || !isset($value['tmp_name'])) {
            $value = $this->getFileUrl();
            $this->setValueReceivedFromDb(true);
        }
        return $value;
    }

    /**
     * @return bool
     * @throws \PeskyORM\Exception\DbFieldException
     */
    public function isUploadedFile() {
        return (!$this->isValueReceivedFromDb() && is_array($this->getValue()) && Utils::isUploadedFile($this->getValue()));
    }

    /**
     * @return bool
     */
    public function isFileExists() {
        return File::exist($this->getFilePath());
    }

    /**
     * Read json file if db object exists
     * @return array|null - array('file_name' => string, 'full_file_name' => string, 'ext' => string)
     */
    protected function getFileInfoFromInfoFile() {
        if (!$this->getDbObject()->exists()) {
            return null;
        } else if (!array_key_exists('file_info', $this->values)) {
            $infoFilePath = $this->getInfoFilePath() ;
            $this->values['file_info'] = null;
            if (File::exist($infoFilePath)) {
                $info = File::readJson($infoFilePath);
                if (!empty($info)) {
                    $this->values['file_info'] = $info;
                }
            }
        }
        return $this->values['file_info'];
    }

    /**
     * @return string
     * @throws DbFieldException
     */
    protected function getInfoFilePath() {
        return $this->getFileDirPath() . $this->getName() . '_' . self::$infoFileName;
    }

    /**
     * Get absolute FS path to file
     * @return string
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
     * @throws DbFieldException
     */
    public function getFileDirPath() {
        if (!$this->getDbObject()->exists()) {
            throw new DbFieldException($this, 'Unable to get file dir path of non-existing object');
        }
        if (empty($this->values['file_dir_path'])) {
            $generator = $this->dbColumnConfig->getFileDirPathGenerator();
            if (!empty($generator)) {
                $dirPath = $generator($this);
                if (empty($dirPath) || !is_string($dirPath)) {
                    throw new DbFieldException($this, "File dir path genetartor function should return not-empty string");
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
     * @throws DbFieldException
     */
    public function getFileDirRelativeUrl() {
        if (!$this->getDbObject()->exists()) {
            throw new DbFieldException($this, 'Unable to get file url of non-existing object');
        }
        if (empty($this->values['file_dir_relative_url'])) {
            $generator = $this->dbColumnConfig->getFileDirRelativeUrlGenerator();
            if (!empty($generator)) {
                $relUrl = $generator($this);
                if (empty($relUrl) || !is_string($relUrl)) {
                    throw new DbFieldException($this, "File dir relative url genetartor function should return not-empty string");
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
     * @throws DbFieldException
     */
    public function getFileDirAbsoluteUrl() {
        if (!$this->getDbObject()->exists()) {
            throw new DbFieldException($this, 'Unable to get file url of non-existing object');
        }
        if (empty($this->values['file_dir_absolute_url'])) {
            $this->values['file_dir_absolute_url'] = rtrim($this->getFileServerUrl(), '/\\') . '/' . $this->getFileDirRelativeUrl() . '/';
        }
        return $this->values['file_dir_absolute_url'];
    }

    /**
     * Get server URL where files are stored (ex: http://sub.server.com)
     * @return string
     */
    public function getFileServerUrl() {
        // todo: implement getFileServerUrl()
        return '';
        // return (!empty($this->model->fields[$field]['server'])) ? \Server::base_url($this->model->fields[$field]['server']) : '';
    }

    /**
     * Get subdir to files based on primary key and maybe some other custom things
     * @param string $directorySeparator - directory separator
     * @return string
     * @throws DbFieldException
     */
    public function getFilesSubdir($directorySeparator = DIRECTORY_SEPARATOR) {
        if (!$this->getDbObject()->exists()) {
            throw new DbFieldException($this, 'Unable to get file subdir of non-existing object');
        }
        $generator = $this->dbColumnConfig->getFileSubdirGenerator();
        if (!empty($generator)) {
            $subdir = $generator($this, $directorySeparator);
            if (empty($subdir) || !is_string($subdir)) {
                throw new DbFieldException($this, "File subdir genetartor function should return not-empty string");
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
     * @throws DbFieldException
     */
    public function getFileNameWithoutExtension($fallbackValue = null) {
        $generator = $this->dbColumnConfig->getFileNameGenerator();
        if (!empty($generator)) {
            $fileName = $generator($this);
            if (empty($fileName) || !is_string($fileName)) {
                throw new DbFieldException($this, "File name genetartor function should return not-empty string");
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
        $fileInfo = $this->getFileInfoFromInfoFile();
        if (!empty($fileInfo) && !empty($fileInfo['full_file_name'])) {
            return $fileInfo['full_file_name'];
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
     * Detect Uploaded file extension by file name or content type
     * @param array $uploadedFileInfo - uploaded file info
     * @return bool|string -
     *      string: file extension without leading point (ex: 'mp4', 'mov', '')
     * false: invalid file info or not supported extension
     * @throws DbFieldException
     */
    public function detectUploadedFileExtension($uploadedFileInfo) {
        if (empty($uploadedFileInfo['type']) && empty($uploadedFileInfo['name']) && empty($uploadedFileInfo['tmp_name'])) {
            return false;
        }
        // test content type
        $receivedExt = false;
        if (!empty($uploadedFileInfo['type'])) {
            $receivedExt = array_search($uploadedFileInfo['type'], self::$extToConetntType);
        }
        if (!empty($uploadedFileInfo['name']) && (empty($receivedExt) || is_numeric($receivedExt))) {
            $receivedExt = preg_match('%\.([a-zA-Z0-9]+)\s*$%is', $uploadedFileInfo['name'], $matches) ? $matches[1] : '';
        } else if (!empty($uploadedFileInfo['tmp_name']) && (empty($receivedExt) || is_numeric($receivedExt))) {
            $receivedExt = preg_match('%\.([a-zA-Z0-9]+)\s*$%is', $uploadedFileInfo['tmp_name'], $matches) ? $matches[1] : '';
        }
        $expectedExts = $this->getAllowedFileExtensions();
        if (!empty($expectedExts)) {
            if (empty($receivedExt)) {
                $receivedExt = $this->getDefaultFileExtension();
            } else if (!in_array($receivedExt, $expectedExts)) {
                throw new DbFieldException(
                    $this,
                    'Uploaded file extension is not allowed',
                    DbExceptionCode::FILE_EXTENSION_NOT_ALLOWED
                );
            }
        }
        return $receivedExt;
    }

    /**
     * @return string
     * @throws DbFieldException
     */
    public function getFileExtension() {
        $fileInfo = $this->getFileInfoFromInfoFile();
        if (!empty($fileInfo) && !empty($fileInfo['ext'])) {
            return $fileInfo['ext'];
        } else {
            $defaultExtension = $this->getDefaultFileExtension();
            if ($this->getDbObject()->exists()) {
                $allowedExtensions = $this->getAllowedFileExtensions();
                if (!empty($allowedExtensions)) {
                    foreach ($allowedExtensions as $ext) {
                        $fileDir = $this->getFileDirPath();
                        $fileNameNoExt = $this->getFileNameWithoutExtension();
                        if (File::exist($fileDir . $fileNameNoExt . '.' . $ext)) {
                            return $ext;
                        }
                    }
                }
            }
            return $defaultExtension;
        }
    }

    /**
     * @return null|string|bool - null: not an upload | string: FS path fo file | false: invalid file uploaded (validation error)
     * @throws DbFieldException
     */
    public function saveUploadedFile() {
        if (!$this->getDbObject()->exists(true)) {
            throw new DbFieldException($this, 'Unable to save file of non-existing object');
        }
        if (!$this->validate(true, true)) {
            return false;
        }
        if (!$this->isUploadedFile()) {
            return null;
        }
        $uploadedFileInfo = $this->getValue();
        if (!defined('UNLIMITED_EXECUTION') || !UNLIMITED_EXECUTION) {
            set_time_limit(90);
            ini_set('memory_limit', '128M');
        }
        $fileInfo = $this->storeFileToFS($uploadedFileInfo);
        if (!empty($fileInfo)) {
            $this->saveUploadedFileInfo($fileInfo);
            $this->setValue($this->getFileUrl(), true);
            return $this->getFilePath();
        }
        return false;
    }

    /**
     * Save file to FS + collect information
     * @param array $uploadedFileInfo - uploaded file info
     * @return bool|array - array: information about file same as when you get by callings $this->getFileInfoFromInfoFile()
     */
    protected function storeFileToFS($uploadedFileInfo) {
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
        } catch (DbFieldException $exc) {
            $this->setValidationError($exc->getMessage());
            return false;
        }
        $filePath = $pathToFiles . $fileName;
        // move tmp file to target file path
        return File::load($uploadedFileInfo['tmp_name'])->move($filePath, 0666) ? $fileInfo : false;
    }

    /**
     * Save $fileInfo to file and to $this->values['file_info']
     * @param array $fileInfo
     * @throws DbFieldException
     */
    protected function saveUploadedFileInfo($fileInfo) {
        if (is_array($fileInfo)) {
            File::saveJson($this->getInfoFilePath(), $fileInfo);
            $this->values['file_info'] = $fileInfo;
        } else {
            throw new DbFieldException($this, '$fileInfo shoud be an array');
        }
    }

    /**
     * Delete files attached to DbObject field
     * @throws DbObjectException
     */
    public function deleteFiles() {
        if (!$this->getDbObject()->exists()) {
            throw new DbFieldException($this, 'Unable to delete files of non-existing object');
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