<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbExceptionCode;
use PeskyORM\Exception\DbFieldException;
use PeskyORM\Lib\File;
use PeskyORM\Lib\Utils;

class FileField extends DbObjectField {

    static public $extToConetntType = array(
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
    );

    public function isValidValueFormat($value) {
        if (empty($value) || !is_array($value) || array_key_exists('tmp_file', $value)) {
            return true;
        }
        $this->setValidationError('File upload expected');
        return false;
    }

    protected function doBasicValueValidationAndConvertion($value) {
        return $this->formatFile($value);
    }

    /**
     * @return bool
     * @throws \PeskyORM\Exception\DbFieldException
     */
    public function isUploadedFile() {
        return (!$this->isValueReceivedFromDb() && is_array($this->getValue()) && Utils::isUploadedFile($this->getValue()));
    }

    /**
     * Get fs path to file
     * @return string
     */
    public function getFilePath() {
        if (!isset($this->values['file_path'])) {
            $this->values['file_path'] = $this->getFileDirPath() . $this->getFullFileName();
        }
        return $this->values['file_path'];
    }

    /**
     * Get url to file
     * @return string
     */
    public function getFileUrl() {
        if (!isset($this->values['file_url'])) {
            $this->values['file_url'] = $this->getFileDirAbsoluteUrl() . $this->getFullFileName();
        }
        return $this->values['file_url'];
    }

    /**
     * Build FS path to files (absolute FS path to folder with files)
     * @return string
     */
    public function getFileDirPath() {
        if (!empty($this->values['file_dir_path'])) {
            if (!$this->getDbObject()->exists()) {
                $this->values['file_dir_path'] = 'undefined.file';
            } else {
                $objectSubdir = $this->getFilesSubdir(DIRECTORY_SEPARATOR);
                if (!empty($objectSubdir)) {
                    $objectSubdir = DIRECTORY_SEPARATOR . trim($objectSubdir, '/\\') . DIRECTORY_SEPARATOR;
                }
                $this->values['file_dir_path'] = rtrim($this->getFilesBasePath(), '/\\') . $objectSubdir;
            }
        }
        return $this->values['file_dir_path'];
    }

    /**
     * Build base url to files (url to folder with files)
     * @return string
     */
    public function getFileDirAbsoluteUrl() {
        if (!empty($this->values['file_dir_absolute_url'])) {
            if (!$this->getDbObject()->exists()) {
                $this->values['file_dir_absolute_url'] = 'undefined.file';
            } else {
                $this->values['file_dir_absolute_url'] = $this->getFileServerUrl() . $this->getFileDirRelativeUrl();
            }
        }
        return $this->values['file_dir_absolute_url'];
    }

    /**
     * Get relative url to files by $field
     * @return string
     */
    public function getFileDirRelativeUrl() {
        if (!empty($this->values['file_dir_relative_url'])) {
            if (!$this->getDbObject()->exists()) {
                $this->values['file_dir_relative_url'] = 'undefined.file';
            } else {
                $objectSubdir = $this->getFilesSubdir('/');
                if (!empty($objectSubdir)) {
                    $objectSubdir = '/' . trim($objectSubdir, '/\\') . '/';
                }
                $this->values['file_dir_relative_url'] = '/' . trim($this->getFilesBasePath(), '/\\') . $objectSubdir;
            }
        }
        return $this->values['file_dir_relative_url'];
    }

    /**
     * Get server url where files are stored (ex: http://sub.server.com)
     * @return string
     */
    public function getFileServerUrl() {
        // todo: implement getFileServerUrl()
        return '';
        // return (!empty($this->model->fields[$field]['server'])) ? \Server::base_url($this->model->fields[$field]['server']) : '';
    }

    /**
     * Get full file name for $field (with suffix and extension)
     * @param string $suffix
     * @return string
     */
    public function getFullFileName($suffix = '') {
        $baseName = $this->getFileName() . $suffix;
        $pathTofiles = $this->getFileDirPath();
        $ext = $this->getDefaultFileExtension();
        if ($ext !== null) {
            $baseName = '.' . $ext;
        } else if (File::exist($pathTofiles . $baseName . '.ext')) {
            $baseName .= File::contents();
        }
        return $baseName;
    }

    /**
     * Get subdir to files based on primary key and maybe some other custom things
     * @param string $ds - directory separator
     * @return string
     */
    public function getFilesSubdir($ds) {
        // todo: implement usage of FileColumnConfig
        return $this->getDbObject()->_getPkValue();
    }

    /**
     * @return bool
     */
    public function isFileExists() {
        return file_exists($this->getFilePath());
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
     * @return string
     * @throws DbFieldException
     */
    public function getFilesBasePath() {
        // todo: implement getFilesBasePath
        // todo throw exception if base path not set or is empty
        throw new DbFieldException($this, "getFilesBasePath() not implemented yet");
        return '';
    }

    /**
     * @param string|callable|null $fallbackValue - null: $this->getName() is used
     * @return string
     * @throws DbFieldException
     */
    public function getFileName($fallbackValue = null) {

        // todo: implement hasCustomFileName
        // todo: throw exception when no file name specified and $fallbackValue is empty or not a string or callable
        throw new DbFieldException($this, "hasCustomFileName() not implemented yet");
//        if (empty($fallbackValue)) {
//            $fallbackValue = $this->getName();
//        }
        return '';
    }

    /**
     * @return string
     * @throws DbFieldException
     */
    public function getFilesBaseUrl() {
        // todo: implement getFilesBaseUrl
        throw new DbFieldException($this, "getFilesBaseUrl() not implemented yet");
        return '';
    }

    /**
     * @return string|null
     * @throws DbFieldException
     */
    public function getDefaultFileExtension() {
        // todo: implement getFilesExtension
        // note: extension could be array of accepted extensions
        // todo: separate accepted extesions and default extension
        throw new DbFieldException($this, "getFilesExtension() not implemented yet");
        return null;
        /*if (!empty($this->_model->fields[$field]['extension'])) {
            if (is_array($this->_model->fields[$field]['extension'])) {
                foreach ($this->_model->fields[$field]['extension'] as $ext) {
                    if (File::exist($pathTofiles . $baseName . '.' . $ext)) {
                        $baseName .= '.' . $ext;
                        break;
                    }
                }
            }
        }
        return $this->_model->fields[$field]['extension'];

        */
    }

    /**
     * @return array
     * @throws DbFieldException
     */
    public function getAllowedFileExtensions() {
        // todo: implement getAllowedFilesExtensions
        throw new DbFieldException($this, "getAllwedFilesExtensions() not implemented yet");
        return array();
    }

    /**
     * Detect Uploaded file extension by file name or content type
     * @param array $fileInfo - uploaded file info
     * @param FileField $fileField - file field info (may contain 'extension' key to limit possible extensions)
     * @param string|bool $saveExtToFile - string: file path to save extension to.
     *      Extension saved to file only when empty($fieldInfo['extension']) and extesion detected
     * @return bool|string -
     *      string: file extension without leading point (ex: 'mp4', 'mov', '')
     * false: invalid file info or not supported extension
     * @throws DbFieldException
     */
    public function detectUploadedFileExtension($fileInfo, $saveExtToFile = false) {
        if (empty($fileInfo['type']) && empty($fileInfo['name'])) {
            return false;
        }
        // test content type
        $receivedExt = false;
        if (!empty($fileInfo['type'])) {
            $receivedExt = array_search($fileInfo['type'], self::$extToConetntType);
        }
        if (!empty($fileInfo['name']) && (empty($receivedExt) || is_numeric($receivedExt))) {
            $receivedExt = preg_match('%\.([a-zA-Z0-9]+)\s*$%is', $fileInfo['name'], $matches) ? $matches[1] : '';
        }
        $expectedExts = $this->getAllowedFileExtensions();
        if (!empty($expectedExts)) {
            if (empty($receivedExt)) {
                $receivedExt = array_shift($expectedExts);
            } else if (!in_array($receivedExt, $expectedExts)) {
                throw new DbFieldException(
                    $this,
                    "Uploaded file has extension [$receivedExt] that is not allowed",
                    DbExceptionCode::FILE_EXTENSION_NOT_ALLOWED
                );
            }
        } else if ($saveExtToFile && !empty($receivedExt)) {
            File::save($saveExtToFile, $receivedExt, 0666);
        }
        return $receivedExt;
    }
}