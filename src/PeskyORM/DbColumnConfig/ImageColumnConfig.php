<?php

namespace PeskyORM\DbColumnConfig;

use Swayok\Utils\ImageVersionConfig;

class ImageColumnConfig extends FileColumnConfig {

    protected $_type = self::TYPE_IMAGE;
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
}