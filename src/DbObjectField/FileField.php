<?php


namespace ORM\DbObjectField;

use PeskyORM\DbObjectField;
use PeskyORM\Lib\Utils;

class FileField extends DbObjectField {

    public function isUploadedFile() {
        return (!$this->values['isDbValue'] && is_array($this->value) && Utils::isUploadedFile($this->value));
    }
}