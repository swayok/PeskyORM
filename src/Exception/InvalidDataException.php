<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

use Swayok\Utils\Set;

class InvalidDataException extends OrmException
{
    
    protected array $errors = [];
    
    public function __construct(array $errors)
    {
        $message = [];
        foreach ($errors as $key => $error) {
            $errorMsg = '';
            if (!is_int($key)) {
                $errorMsg = '[' . $key . '] ';
            }
            if (is_array($error)) {
                $error = implode(', ', Set::flatten($error));
            }
            $errorMsg .= $error;
            $message[] = $errorMsg;
        }
        parent::__construct(static::MESSAGE_INVALID_DATA . implode('; ', $message), static::CODE_INVALID_DATA);
        $this->errors = $errors;
    }
    
    /**
     * @param bool $flatten - false: return errors as is; true: flatten errors (2 levels only)
     * Example: $errors = ['images' => ['source.0' => ['error message1', 'error message 2'], 'source.1' => ['err']];
     * returned array will be: ['images.source.0' => ['error message1', 'error message 2'], 'images.source.1' => ['err']]
     * @return array
     */
    public function getErrors(bool $flatten = true): array
    {
        if (!$flatten) {
            return $this->errors;
        }
        $flatErrors = [];
        foreach ($this->errors as $columnName => $errors) {
            if (isset($errors[0])) {
                $flatErrors[$columnName] = $errors;
            } else {
                foreach ($errors as $subKey => $realErrors) {
                    $flatErrors[$columnName . '.' . $subKey] = $realErrors;
                }
            }
        }
        return $flatErrors;
    }
    
}