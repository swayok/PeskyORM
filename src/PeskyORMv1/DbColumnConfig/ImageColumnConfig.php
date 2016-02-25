<?php


namespace PeskyORM\DbColumnConfig;

use PeskyORM\DbColumnConfig;
use PeskyORM\Exception\DbColumnConfigException;
use Swayok\Utils\ImageVersionConfig;

class ImageColumnConfig extends FileColumnConfig {

    protected $_type = self::TYPE_IMAGE;
    /**
     * @var ImageVersionConfig[]
     */
    private $versionsConfigs = array();

    /**
     * @param string $versionName
     * @param ImageVersionConfig $config
     * @return $this
     * @throws DbColumnConfigException
     */
    public function addImageVersionConfig($versionName, ImageVersionConfig $config) {
        if ($this->hasImageVersionConfig($versionName)) {
            throw new DbColumnConfigException($this, "Image version with name [$versionName] already defined");
        }
        $this->versionsConfigs[$versionName] = $config;
        return $this;
    }

    /**
     * @return ImageVersionConfig[]
     */
    public function getImageVersionsConfigs() {
        return $this->versionsConfigs;
    }

    /**
     * @param string $versionName
     * @return bool
     */
    public function hasImageVersionConfig($versionName) {
        return !empty($this->versionsConfigs[$versionName]);
    }

    /**
     * @param string $versionName
     * @return \Swayok\Utils\ImageVersionConfig
     * @throws DbColumnConfigException
     */
    public function getImageVersionConfig($versionName) {
        if (!$this->hasImageVersionConfig($versionName)) {
            throw new DbColumnConfigException($this, "Image version with name [$versionName] is not defined");
        }
        return $this->versionsConfigs[$versionName];
    }
}