<?php

namespace PeskyORM\DbColumnConfig;

use PeskyORM\DbImageFileInfo;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\RecordValue;
use Swayok\Utils\ImageUtils;
use Swayok\Utils\ImageVersionConfig;

class ImageColumnConfig extends FileColumnConfig {

    protected $_type = self::TYPE_IMAGE;
    
    protected $fileInfoClassName = DbImageFileInfo::class;
    /**
     * @var ImageVersionConfig[]
     */
    private $versionsConfigs = [];

    /**
     * @param string $versionName
     * @param ImageVersionConfig $config
     * @return $this
     */
    public function addImageVersionConfig(string $versionName, ImageVersionConfig $config) {
        if ($this->hasImageVersionConfig($versionName)) {
            throw new \InvalidArgumentException("Image version config '$versionName' already defined");
        }
        $this->versionsConfigs[$versionName] = $config;
        return $this;
    }

    /**
     * @return ImageVersionConfig[]
     */
    public function getImageVersionsConfigs(): array {
        return $this->versionsConfigs;
    }

    public function hasImageVersionConfig(string $versionName): bool {
        return !empty($this->versionsConfigs[$versionName]);
    }

    public function getImageVersionConfig($versionName): ImageVersionConfig {
        if (!$this->hasImageVersionConfig($versionName)) {
            throw new \InvalidArgumentException("Image version config '$versionName' is not defined");
        }
        return $this->versionsConfigs[$versionName];
    }
    
    /**
     * @param Record $record
     * @param string|null $versionName
     * @return array|string|null
     */
    public function getImageVersionPath(Record $record, ?string $versionName) {
        $paths = $this->getImagesPaths($record);
        if (empty($versionName)) {
            return $paths;
        } else if (!empty($paths[$versionName])) {
            return $paths[$versionName];
        } else {
            return null;
        }
    }
    
    public function getImagesPaths(Record $record): array {
        $this->requireRecordExistence($record);
        return ImageUtils::getVersionsPaths(
            $this->getFileDirPath($record),
            $this->getFileNameWithoutExtension(),
            $this->getImageVersionsConfigs()
        );
    }
    
    /**
     * @param RecordValue $valueContainer
     * @param null $versionName
     * @return string|string[]|null
     */
    public function getAbsoluteFileUrl(RecordValue $valueContainer, ?string $versionName = null) {
        $relativeUrl = $this->getRelativeImageUrl($valueContainer->getRecord(), $versionName);
        $serverUrl = $this->getFileServerUrl();
        if (is_array($relativeUrl)) {
            $ret = [];
            foreach ($relativeUrl as $version => $url) {
                if (!$this->isAbsoluteUrl($url)) {
                    $ret[$version] = $serverUrl . $url;
                } else {
                    $ret[$version] = $url;
                }
            }
            return $ret;
        } else if (empty($relativeUrl)) {
            return null;
        } else if (!$this->isAbsoluteUrl($relativeUrl)) {
            return $serverUrl . $relativeUrl;
        } else {
            return $relativeUrl;
        }
    }
    
    /**
     * @param Record $record
     * @param string|null $versionName
     * @return string[]|string|null
     */
    protected function getRelativeImageUrl(Record $record, ?string $versionName) {
        $urls = $this->getRelativeImagesUrls($record);
        if (empty($versionName)) {
            return $urls;
        } else if (!empty($urls[$versionName])) {
            return $urls[$versionName];
        } else {
            return null;
        }
    }
    
    protected function getRelativeImagesUrls(Record $record): array {
        $this->requireRecordExistence($record);
        return ImageUtils::getVersionsUrls(
            $this->getFileDirPath($record),
            $this->getFileDirRelativeUrl($record),
            $this->getFileNameWithoutExtension(),
            $this->getImageVersionsConfigs()
        );
    }
}