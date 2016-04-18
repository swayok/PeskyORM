<?php

namespace PeskyORM\Core;

class Utils {

    const FETCH_ALL = 'all';
    const FETCH_FIRST = 'first';
    const FETCH_VALUE = 'value';
    const FETCH_COLUMN = 'column';

    /**
     * Get data from $statement according to required $type
     * @param \PDOStatement $statement
     * @param string $type = 'first', 'all', 'value', 'column'
     * @return array|string
     * @throws \InvalidArgumentException
     */
    static public function getDataFromStatement(\PDOStatement $statement, $type = self::FETCH_ALL) {
        $type = strtolower($type);
        if (!in_array($type, array(self::FETCH_COLUMN, self::FETCH_ALL, self::FETCH_FIRST, self::FETCH_VALUE), true)) {
            throw new \InvalidArgumentException("Unknown processing type [{$type}]");
        }
        if ($statement && $statement->rowCount() > 0) {
            switch ($type) {
                case self::FETCH_COLUMN:
                    return $statement->fetchAll(\PDO::FETCH_COLUMN);
                case self::FETCH_VALUE:
                    return $statement->fetchColumn(0);
                case self::FETCH_FIRST:
                    return $statement->fetch(\PDO::FETCH_ASSOC);
                case self::FETCH_ALL:
                default:
                    return $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
        } else if ($type === self::FETCH_VALUE) {
            return null;
        } else {
            return array();
        }
    }
}