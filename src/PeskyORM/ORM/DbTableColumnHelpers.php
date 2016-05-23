<?php

namespace PeskyORM\ORM;

abstract class DbTableColumnHelpers {

    static public function getNewValueProcessorForType($type) {
        switch ($type) {
            case DbTableColumn::TYPE_BOOL:
                return function ($newValue, DbTableColumn $column, DbRecord $record) {
                    return static::processBool($newValue, $column, $record);
                };
        }
    }

    static public function getValueValidatorForType($type) {
        switch ($type) {
            case DbTableColumn::TYPE_BOOL:
                return function ($newValue, DbTableColumn $column, DbRecord $record) {
                    return static::validateBool($newValue, $column, $record);
                };
        }
    }
}