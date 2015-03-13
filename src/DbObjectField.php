<?php

namespace PeskyORM;

use PeskyORM\Exception\DbFieldException;
use PeskyORM\Lib\ImageUtils;
use PeskyORM\Lib\Utils;

/**
 * Class DbObjectField
 * @property mixed $value - value after type conversion
 * @property mixed $isDbValue - true: field value differs from field value in DB | false: field value is DB field value
 * @property-read mixed $rawValue - value in format it was assigned (without type conversion)
 * @property-read array $options - list of allowed values
 * @property-read null|string $validationError - validation error message
 * @property-read null|string $_date - date part of datetime field
 * @property-read null|string $_time - time part of datetime field
 * @property-read null|int $_ts - timestamp (only for date/time fields)
 * @property-read null|string $_file_path - full FS path to file
 * @property-read null|bool $_file_exists - test if file exists (only if file placed on current server, otherwise true will be returned)
 */
class DbObjectField {
    /**
     * @var DbObject
     */
    protected $dbObject;
    public $_info;              //< all information about field
    public $name;
    public $type;               //< one of DbObjectField::TYPE_*
    public $isPk = false;       //< indicates that field is a primary key
    public $isFile = false;     //< indicates that field is file (virtual field)
    public $isImage = false;    //< indicates that field is file (virtual field)
    public $server = null;     //< server alies where file stored
    public $isUnique = false;   //< indicates that field contains only unique values + casts empty values to null if possible
    public $isVirtual = false;   //< indicates that field is virtual (does not exist in db)
    public $importValueFromDbObject = false;   //< for virtual fields only. string: field value should be imported from another field
    public $default;            //< note: for $type == self::TYPE_TIMESTAMP can accept any value that can be passed to strtotime()
    public $length = 0;
    public $null = true;        //< false means that in DB field is "NOT NULL"
    protected $values = array(
        //'value' => mixed,         //< value after $this->convert()
        //'rawValue' => mixed,      //< value in format it was assigned (without type conversion)
        //'rawDbValue' => mixed,    //< raw value from DB - assigned on $this->isDbValue = true
        //'options' => array,       //< List of allowed values for $type == self::TYPE_ENUM
        //'error' => null|string,   //< validation error
        //'file_path' => null|string|array,   //< fs path to files
        'isset' => false,           //< used to find out if value was ever set. When true: __isset() will return true
        'isDbValue' => false,       //< indicates that field value must be updated in db
    );
    protected $relations = array(); //< list of related DbObjects aliases. DbObjects stored in model

    /**
     * self::ON_NONE - allows field to be 'null' (if $null == true), unset or empty string
     * self::ON_ALL - field is required for both creation and update
     * self::ON_CREATE - field is required only for creation
     * self::ON_UPDATE - field is required only for update
     * @var int
     */
    public $required = self::ON_NONE;

    /**
     * self::ON_NONE - forced skip disabled
     * self::ON_ALL - forced skip enabled for any operation
     * self::ON_CREATE - forced skip enabled for record creation only
     * self::ON_UPDATE - forced skip enable for record update only
     * @var int
     */
    public $exclude = self::ON_NONE;

    public $validators = array();

    const NULL_VALUE = 'NULL';

    const FORMAT_TIMESTAMP = 'Y-m-d H:i:s';
    const FORMAT_DATE = 'Y-m-d';
    const FORMAT_TIME = 'H:i:s';

    const ERROR_REQUIRED = '@!db.field_error.required@';
    const ERROR_NOT_NULL = '@!db.field_error.not_null@';
    const ERROR_TOO_LONG = '@!db.field_error.too_long@';
    const ERROR_INVALID_DATA_FORMAT = '@!db.field_error.invalid_format@';
    const ERROR_INVALID_EMAIL = '@!db.field_error.invalid_email@';
    const ERROR_INVALID_DB_ENTITY_NAME = '@!db.field_error.invalid_db_entity_name@';
    const ERROR_INVALID_JSON = '@!db.field_error.invalid_json@';
    const ERROR_INVALID_DATETIME = '@!db.field_error.invalid_datetime@';
    const ERROR_VALUE_NOT_ALLOWED = '@!db.field_error.value_not_allowed@';
    const ERROR_INVALID_IP_ADDRESS = '@!db.field_error.invalid_ip_address@';
    const ERROR_NO_IMAGE_OR_NOT_SUPPORTED_TYPE = '@!db.field_error.no_image_or_not_suppoted_type@';
    const ERROR_DUPLICATE_VALUE_FOR_UNIQUE_FIELD = '@!db.field_error.duplicate_value_for_unique_field@';

//    const REGEXP_EMAIL = '%^(([^<>()\[\].,;:\s@"*\'#$\%\^&=+\\\/!\?]+(\.[^<>()\[\],;:\s@"*\'#$\%\^&=+\\\/!\?]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$%is';
    const REGEXP_EMAIL = "%^[a-z0-9!#\$\%&'*+/=?\^_`{|}~-]+(?:\.[a-z0-9!#\$\%&'*+/=?\^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$%is"; //< http://www.regular-expressions.info/email.html
    const REGEXP_DB_ENTITY_NAME = '%^[a-z][a-z0-9_]*$%';
    const REGEXP_IP_ADDRESS = '%^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$%is';
    const REGEXP_TIMESTAMP = '%^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2}(\.\d+)?)?$%is';

    /**
     * @param DbObject $dbObject
     * @param string $name
     * @param array $info - field settings:
     * array(
     *      'type': int|string (required)
     *      'options': array (required only for 'type' == self::TYPE_ENUM)
     *      'length': int (optional, default = 0) - max field length, 0 = not inportant
     *      'null': bool (optional, default = true) - can this field be 'null'?
     *      'required': bool (optional, default = false) - is this field required?
     *      'default': mixed (optional) - default field value
     *      'validators': array (optional, default: array()) - list of validators
     */
    public function __construct(DbObject $dbObject, $name, $info) {
        $this->_info = $info;
        $this->dbObject = $dbObject;
        $this->name = $name;
        $this->type = strtolower($info['type']);
        $this->isFile = in_array($this->type, self::$fileTypes);
        $this->isImage = in_array($this->type, self::$imageFileTypes);
        if (!empty($info['server'])) {
            $this->server = $info['server'];
        }
        $this->isVirtual = !empty($info['virtual']);
        if (
            $this->isVirtual
            && !$this->isFile
            && (
                is_string($info['virtual']) && !is_numeric($info['virtual'])
                || $info['virtual'] === true
            )
        ) {
            $this->importValueFromDbObject = $info['virtual'];
        }
        if (!empty($info['options']) && is_array($info['options'])) {
            $this->values['options'] = array_values($info['options']);
        }
        $this->length = empty($info['length']) ? 0 : intval($info['length']);
        $this->null = !empty($info['null']);
        $this->isUnique = !empty($info['unique']);
        if (array_key_exists('required', $info) && $info['required'] <= self::ON_UPDATE && $info['required'] >= self::ON_NONE) {
            $this->required = intval($info['required']);
        }
        if (array_key_exists('exclude', $info) && $info['exclude'] <= self::ON_UPDATE && $info['exclude'] >= self::ON_NONE) {
            $this->exclude = intval($info['exclude']);
        }
        if (!empty($info['validators']) && is_array($info['validators'])) {
            $this->validators = $info['validators'];
        }
        if (array_key_exists('default', $info)) {
            $this->default = $this->convert($info['default']);
        }
    }

    public function getDataSource() {
        return $this->dbObject->model->getDataSource();
    }

    /**
     * @return DbObject
     */
    public function getDbObject() {
        return $this->dbObject;
    }

    /**
     * Add alias of related object
     * Object itself stored in model
     * @param string $alias
     */
    public function addRelation($alias) {
        if (!in_array($alias, $this->relations)) {
            $this->relations[] = $alias;
        }
    }

    /**
     * Get aliases of all relations
     * @return array
     */
    public function getRelations() {
        return $this->relations;
    }

    /**
     * Reset field value to default value or unset
     */
    public function reset() {
        unset($this->value);
    }

    /**
     * Set field value
     * @param string $name = 'value'
     * @param mixed $value
     * @throws DbFieldException
     */
    public function __set($name, $value) {
        switch (strtolower($name)) {
            case 'value':
                if ($this->isVirtual && $this->importValueFromDbObject) {
                    break;
                }
                $this->values['isset'] = true;
                $this->values['rawValue'] = $value;
                $this->values['value'] = $this->convert($this->values['rawValue']);
                if ($this->isPk && !isset($this->values['value'])) {
                    // null pk value may cause non null violation in db - we don't need it to happen
                    unset($this->value);
                } else {
                    $this->validate();
                    if (!array_key_exists('dbValue', $this->values) || $this->values['dbValue'] !== $this->values['value']) {
                        $this->values['isDbValue'] = false; //< value was updated
                        $this->dbObject->fieldUpdated($this->name);
                    }
                }
                break;
            case 'isdbvalue':
                if (!empty($this->values['isset'])) {
                    $this->values['isDbValue'] = !empty($value);
                    if (isset($this->values['value'])) {
                        $this->values['dbValue'] = $this->values['value'];
                    }
                }
                break;
        }
    }

    /**
     * Get field value
     * @param string $name = 'value' | 'rawValue'
     * @return mixed
     * @throws DbFieldException if value is not set or invalid
     */
    public function __get($name) {
        switch (strtolower($name)) {
            case 'value':
                if ($this->isVirtual && $this->importValueFromDbObject) {
                    if ($this->importValueFromDbObject === true) {
                        return $this->dbObject->{'_' . $this->name}();
                    } else {
                        return $this->dbObject->{$this->importValueFromDbObject};
                    }
                }
                if (empty($this->values['isset']) && $this->name !== $this->dbObject->model->getPkColumn()) {
                    // value not set and not a primary key
                    if ($this->dbObject->exists()) {
                        // on object update
                        if (in_array($this->type, self::$fileTypes)) {
                            // import pk form object to create image urls and fill value
                            $this->importPkValue();
                        } else {
                            // value is set in db but possibly was not fetched
                            // to avoid overwriting of correct value object must notify about this situation
                            $error = "Field [{$this->dbObject->model->alias}->{$this->name}]: value is not set. Possibly value was not fetched from DB";
                            throw new DbFieldException($this, $error);
                        }
                    } else {
                        // on object create just set default value even it is not valid.
                        // __set('value') will process exceptional situations
                        $this->value = $this->default;
                    }
                }
                return isset($this->values['value']) ? $this->values['value'] : null;
            case 'rawvalue':
                $this->value; //< do processing of [case 'value'] above
                return $this->values['rawValue'];
            case 'isdbvalue':
                return !empty($this->values['isDbValue']);
            case '_file_path':
                return ($this->isFile) ? $this->getFilePath() : null;
            case '_file_exists':
                if ($this->isFile) {
                    $path = $this->getFilePath();
                    return file_exists($this->isImage ? $path['original'] : $path);
                }
                return false;
            case '_date':
                return date('Y-m-d', $this->_ts);
            case '_time':
                return date('H:i:s', $this->_ts);
            case '_ts':
                return isset($this->values['value']) ? strtotime($this->values['value']) : 0;
            case 'validationerror':
                return (isset($this->values['error'])) ? $this->values['error'] : null;
            case 'options':
                return !isset($this->values['options']) || !is_array($this->values['options'])
                    ? array()
                    : $this->values['options'];
        }
        return null;
    }

    /**
     * Imports db object's PK value to use it as field's value
     * @return null|string|int - pk value
     */
    protected function importPkValue() {
        $this->value = $this->dbObject->pkValue();
    }

    /**
     * Unset field value
     * @param string $varName = 'value'
     */
    public function __unset($varName) {
        if (strtolower($varName) == 'value') {
            $cleanValues = array(
                'isset' => false,
                'isDbValue' => false
            );
            if (!empty($this->values['options'])) {
                $cleanValues['options'] = $this->values['options'];
            }
            $this->values = $cleanValues;
        }
    }

    /**
     * Check if field value isset
     * @param string $varName = 'value'
     * @return bool
     * @throws DbFieldException if $varName !== 'value'
     */
    public function __isset($varName) {
        switch (strtolower($varName)) {
            case 'value':
                if ($this->isVirtual && $this->importValueFromDbObject) {
                    if ($this->importValueFromDbObject === true) {
                        return $this->dbObject->{'_' . $this->name}() !== null;
                    } else {
                        return isset($this->dbObject->{$this->importValueFromDbObject});
                    }
                } else {
                    return !empty($this->values['isset']);
                }
            case 'validationerror':
                return isset($this->values['error']);
            default:
            throw new DbFieldException($this, "Field [{$this->name}]: unknown property [$varName]");
        }
    }

    /**
     * Convert field value to $type
     * @param mixed $value
     * @return bool|float|int|string
     * @throws DbFieldException
     */
    public function convert($value) {
        if ($value === self::NULL_VALUE) {
            $value = null;
        }
        if ($value === null && !$this->null) {
            if (isset($this->default)) {
                $value = $this->default;
            } else {
                switch ($this->type) {
                    case 'bool':
                    case 'boolean':
                    case self::TYPE_BOOL:
                        $value = false;
                        break;
                }
            }
        }
        if ($value !== null) {
            switch ($this->type) {
                case 'string':
                case 'text':
                case 'varchar':
                case 'db_name':
                case self::TYPE_STRING:
                case self::TYPE_DB_ENTITY_NAME:
                case self::TYPE_TEXT:
                case 'email':
                case self::TYPE_EMAIL:
                case 'enum':
                case self::TYPE_ENUM:
                case 'ip':
                case self::TYPE_IP_ADDRESS:
                case self::TYPE_SHA1:
                case 'sha1':
                    $value = '' . $value;
                    break;
                case 'json':
                case self::TYPE_JSON:
                    if (is_array($value)) {
                        $value = Utils::jsonEncodeCyrillic($value);
                    }
                    break;
                case 'int':
                case 'integer':
                case self::TYPE_INT:
                    $value = ($value === '') ? null : intval($value);
                    break;
                case 'float':
                case 'number':
                case 'decimal':
                case self::TYPE_FLOAT:
                    $value = floatval($value);
                    break;
                case 'bool':
                case 'boolean':
                case self::TYPE_BOOL:
                    if (is_string($value) && strtolower($value) === 'false') {
                        $value = false;
                    } else if ($value === '' || $value === null) {
                        $value = null;
                    } else {
                        $value = !empty($value);
                    }
                    break;
                case 'timestamp':
                case 'datetime':
                case self::TYPE_TIMESTAMP:
                    $value = $this->formatDateTime($value, self::FORMAT_TIMESTAMP);
                    break;
                case 'time':
                case self::TYPE_TIME:
                    $value = $this->formatDateTime($value, self::FORMAT_TIME);
                    break;
                case 'date':
                case self::TYPE_DATE:
                    $value = $this->formatDateTime($value, self::FORMAT_DATE);
                    break;
                case 'timezone':
                case self::TYPE_TIMEZONE_OFFSET:
                    $value = $this->formatDateTime($value, self::FORMAT_TIME, 0);
                    break;
                case 'file':
                case self::TYPE_FILE:
                case 'image':
                case self::TYPE_IMAGE:
                    $value = $this->formatFile($value);
                    break;
                default:
                    throw new DbFieldException($this, "Field [{$this->name}]: Unknown field type: [{$this->type}]");
            }
            if ($this->isUnique && empty($value) && $this->null) {
                $value = null;
            }
        }
        return $value;
    }

    /**
     * Converts $value to required date-time format
     * @param int|string $value - int: unix timestamp | string: valid date/time/date-time string
     * @param string $format - resulting value format
     * @param string|int|bool $now - current unix timestamp or any valid strtotime() string
     * @return string
     */
    protected function formatDateTime($value, $format, $now = 'now') {
        if (empty($value)) {
            $value = null;
        } else if (is_int($value) || is_numeric($value)) {
            $value = date($format, $value);
        } else if (strtotime($value) != 0) {
            // convert string value to unix timestamp and then to required date format
            $value = date($format, strtotime($value, is_string($now) && !is_numeric($now) ? strtotime($now) : 0));
        }
        return $value;
    }

    /**
     * Format file info
     * @param $value
     * @return array - if image uploaded - image inf, else - urls to image versions
     */
    protected function formatFile($value) {
        if (!is_array($value) || !isset($value['tmp_name'])) {
            if ($this->isImage) {
                $value = $this->dbObject->getImagesUrl($this->name);
            } else {
                $value = $this->dbObject->getFileUrl($this->name);
            }
            $this->isDbValue = true;
        }
        return $value;
    }

    /**
     * Get fs path to file
     * @return mixed
     */
    protected function getFilePath() {
        if (!$this->isFile) {
            return null;
        }
        if (!isset($this->values['file_path'])) {
            if ($this->isImage) {
                $this->values['file_path'] = $this->dbObject->getImagesPaths($this->name);
            } else {
                $this->values['file_path'] = $this->dbObject->getFilePath($this->name);
            }
        }
        return $this->values['file_path'];
    }

    /**
     * Validate field value using $this->null, $this->required, $this->type, $this->validators
     * @param bool $silent - true: do not throw exception
     * @param bool $forSave - true: allow additional validations (like isUnique)
     * @return bool
     * @throws DbFieldException if $silent == false and value is invalid
     */
    public function validate($silent = true, $forSave = false) {
        unset($this->values['error']);
        // skip validation if value is not set or it is a db value (isDbValue is reliable enough to be used)
        if (empty($this->values['isset']) || !empty($this->values['isDbValue'])) {
            return true;
        }
        if (!$this->_validRequired()) {
            $this->values['error'] = self::ERROR_REQUIRED;
        } else if (!$this->null && !isset($this->values['value'])) {
            $this->values['error'] = self::ERROR_NOT_NULL;
        } else if (!$this->_validMaxLength()) {
            $this->values['error'] = self::ERROR_TOO_LONG;
        } else if (!$this->_validDataFormat()) {
            if (empty($this->values['error'])) {
                $this->values['error'] = self::ERROR_INVALID_DATA_FORMAT;
            }
        } else if ($forSave && !$this->_isUnique()) {
            $this->values['error'] = self::ERROR_DUPLICATE_VALUE_FOR_UNIQUE_FIELD;
        } else {
            $this->_validateCustom($silent, $forSave);
        }
        if (!empty($this->values['error']) && !$silent) {
            throw new DbFieldException($this, $this->values['error']);
        }
        return empty($this->values['error']);
    }

    /**
     * Check if field length does not exceeds $this->length
     * @return bool
     */
    protected function _validMaxLength() {
        if (
            $this->length > 0
            && !empty($this->values['isset'])
        ) {
            if (in_array($this->type, array(self::TYPE_STRING, self::TYPE_TEXT, 'string', 'text', 'varchar'))) {
                return mb_strlen($this->values['value']) <= $this->length;
            } else if (in_array($this->type, array(self::TYPE_INT, 'integer'))) {
                return mb_strlen("{$this->values['value']}") <= $this->length;
            }
        }
        return true;
    }

    /**
     * Check if required field is not empty (based on $this->required)
     * Returns true if field not required
     * @return bool
     */
    protected function _validRequired() {
        $valid = true;
        if ($this->required) {
            $valid = isset($this->values['value']) && (!empty($this->values['value']) || is_bool($this->values['value']) || is_numeric($this->values['value']));
            if (!$valid && $this->required !== self::ON_ALL) {
                if ($this->dbObject->exists(false)) {
                    $valid = $this->required !== self::ON_UPDATE;
                } else {
                    $valid = $this->required !== self::ON_CREATE;
                }
            }
        }
        return $valid;
    }

    /**
     * Test if value is unique
     * @return bool
     */
    protected function _isUnique() {
        $valid = true;
        if ($this->isUnique && !empty($this->values['value'])) {
            if (
                !is_numeric($this->values['value'])
                && !in_array($this->type, array(self::TYPE_IP_ADDRESS, self::TYPE_DATE, self::TYPE_TIME, self::TYPE_TIMESTAMP))
            ) {
                $conditions = array(
                    'OR' => array(
                        $this->name => $this->values['value'],
                        DbExpr::create("lower(`{$this->name}`) = lower(``{$this->values['value']}``)")
                    )
                );
            } else {
                $conditions = array(
                    $this->name => $this->values['value'],
                );
            }
            if ($this->dbObject->exists()) {
                $conditions[$this->dbObject->model->getPkColumn() . '!='] = $this->dbObject->pkValue();
            }
            $valid = $this->dbObject->model->count($conditions) == 0;
        }
        return $valid;
    }

    /**
     * Check if value has valid format
     * @return bool
     * @throws DbFieldException
     */
    protected function _validDataFormat() {
        $valid = true;
        if (!empty($this->values['value'])) {
            switch ($this->type) {
                case 'string':
                case 'text':
                case 'varchar':
                case self::TYPE_STRING:
                case self::TYPE_TEXT:
                    break;
                case self::TYPE_SHA1:
                case 'sha1':
                    if (strlen($this->values['value']) !== 40 || !preg_match('%^[a-f0-9]+$%is', $this->values['value'])) {
                        $valid = false;
                        $this->values['error'] = self::ERROR_VALUE_NOT_ALLOWED;
                    }
                    break;
                case 'json':
                case self::TYPE_JSON:
                    if (json_decode($this->values['value'], true) === false) {
                        $valid = false;
                        $this->values['error'] = self::ERROR_INVALID_JSON;
                    }
                    break;
                case 'email':
                case self::TYPE_EMAIL:
                    if (!preg_match(self::REGEXP_EMAIL, $this->values['value'])) {
                        $valid = false;
                        $this->values['error'] = self::ERROR_INVALID_EMAIL;
                    }
                    break;
                case 'db_name':
                case self::TYPE_DB_ENTITY_NAME:
                    if (!preg_match(self::REGEXP_DB_ENTITY_NAME, $this->values['value'])) {
                        $valid = false;
                        $this->values['error'] = self::ERROR_INVALID_DB_ENTITY_NAME;
                    }
                    break;
                case 'int':
                case 'integer':
                case self::TYPE_INT:
                    break;
                case 'float':
                case 'number':
                case 'decimal':
                case self::TYPE_FLOAT:
                    break;
                case 'bool':
                case 'boolean':
                case self::TYPE_BOOL:
                    break;
                case 'timestamp':
                case 'datetime':
                case self::TYPE_TIMESTAMP:
                case 'date':
                case self::TYPE_DATE:
                case 'time':
                case self::TYPE_TIME:
                case self::TYPE_TIMEZONE_OFFSET:
                    if (strtotime($this->values['value']) == 0) {
                        $valid = false;
                        $this->values['error'] = self::ERROR_INVALID_DATETIME;
                    }
                    break;
                case 'enum':
                case self::TYPE_ENUM:
                    if (empty($this->values['options']) || !in_array($this->values['value'], $this->values['options'])) {
                        $valid = false;
                        $this->values['error'] = self::ERROR_VALUE_NOT_ALLOWED;
                    }
                    break;
                case 'ip':
                case self::TYPE_IP_ADDRESS:
                    if (!preg_match(self::REGEXP_IP_ADDRESS, $this->values['value'])) {
                        $valid = false;
                        $this->values['error'] = self::ERROR_INVALID_IP_ADDRESS;
                    }
                    break;
                case 'file':
                case self::TYPE_FILE:
                    // todo: maybe some file validation?
                    break;
                case 'image':
                case self::TYPE_IMAGE:
                    if (is_array($this->values['value']) && array_key_exists('tmp_file', $this->values['value'])) {
                        $valid = ImageUtils::isImage($this->values['value']);
                        if (!$valid) {
                            $this->values['error'] = self::ERROR_NO_IMAGE_OR_NOT_SUPPORTED_TYPE;
                        }
                    }
                    break;
                default:
                    throw new DbFieldException($this, "Field [{$this->name}]: Unknown field type: [{$this->type}]");
            }
        }
        return $valid;
    }

    /**
     * Apply custom validators
     * @param bool $silent - true: do not throw exception
     * @param bool $forSave - true: allow additional validations (like isUnique)
     * @return bool
     */
    protected function _validateCustom($silent = true, $forSave = false) {
        //todo: implement custom validators
        return true;
    }

    /**
     * If this field can be skipped when value is not set and not requred
     * @param bool $skipIfDbValue - true: if field value isDbValue == true - it will be skipped
     * @return bool
     */
    public function skip($skipIfDbValue = false) {
        if (
            $this->exclude == self::ON_ALL
            || $this->exclude == ($this->dbObject->exists() ? self::ON_UPDATE : self::ON_CREATE)
            || $this->isFile
            || ($skipIfDbValue && !empty($this->values['isDbValue']))) {
            return true;
        } else {
            // skip on create/update when not set and not required
            return empty($this->values['isset']) && $this->_validRequired();
        }
    }

    public function isUploadedFile() {
        return (!$this->values['isDbValue'] && is_array($this->value) && self::isUploadedFile($this->value));
    }

    /**
     * Restore image version by name
     * @param string $fileName
     * @return bool|string - false: fail | string: file path
     */
    public function restoreImageVersionByFileName($fileName) {
        if ($this->isImage && !empty($fileName)) {
            // find resize profile
            return ImageUtils::restoreVersion(
                $fileName,
                $this->dbObject->getBaseFileName($this->name),
                $this->dbObject->buildPathToFiles($this->name),
                $this->_info['resize_settings']
            );
        }
        return false;
    }
}