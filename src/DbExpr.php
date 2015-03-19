﻿<?php
namespace PeskyORM;

/**
 * Class Expr
 * @package Db
 *
 * Used to add custom expressions to db queries
 * Why needed: for security reasons DB expressions should be protected from sql injections.
 * It is musch easier to make a special class for expressions so you can only use them from php code
 * and eliminate possibility of sql injections
 * How to use: pass expression to constructor.
 * 1. DB entities (field names, tables, etc) should be queted by single `. For example: `table` or `field`
 * 2. Values should be quoted with double `. Example: ``value``
 * Usage example: `field1` REGEXP ``regexp``
 * (this is simple example. class designed to be used for more complicated expressions)
 */
class DbExpr {

    protected $expression = '';

    static public function create($expression) {
        return new DbExpr($expression);
    }

    public function __construct($expression) {
        $this->expression = $expression;
    }

    public function get() {
        return $this->expression;
    }

    public function __toString() {
        return $this->expression;
    }
}