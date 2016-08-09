<?php
namespace PeskyORM\Core;

/**
 * Used to add custom expressions to db queries
 * Why needed: for security reasons DB expressions should be protected from sql injections.
 * It is musch easier to make a special class for expressions so you can only use them from php code
 * and eliminate possibility of sql injections
 * How to use: pass expression to constructor.
 * 1. DB entities (field names, tables, etc) should be queted by single ` (quote on tilda "~" key).
 * For example: `table` or `field`
 * 2. Values should be quoted with double `. Example: ``value``
 * Usage example: `field1` REGEXP ``regexp``
 * (this is simple example. class designed to be used for more complicated expressions)
 */
class DbExpr {

    /**
     * @var string
     */
    protected $expression = '';
    protected $wrapInBrackets = true;

    /**
     * @param string $expression
     * @param bool|null $wrapInBrackets - true: wrap expression in round brackets; null: autodetect;
     * @return DbExpr
     */
    static public function create($expression, $wrapInBrackets = null) {
        return new DbExpr($expression, $wrapInBrackets);
    }

    /**
     * DbExpr constructor.
     * @param bool|null $wrapInBrackets - true: wrap expression in round brackets; null: autodetect;
     * @param string $expression
     */
    public function __construct($expression, $wrapInBrackets = null) {
        $this->expression = $expression;
        if ($wrapInBrackets === null) {
            $wrapInBrackets = !preg_match(
                '%^\s*(SELECT|INSERT|WITH|UPDATE|DELETE|DROP|ALTER|ORDER|GROUP|HAVING|LIMIT|OFFSET|WHERE)\s%i',
                $expression
            );
        }
        $this->setWrapInBrackets($wrapInBrackets);
    }

    /**
     * @param bool $wrapInBrackets
     * @return $this
     */
    public function setWrapInBrackets($wrapInBrackets) {
        $this->wrapInBrackets = (bool)$wrapInBrackets;
        return $this;
    }

    /**
     * @return string
     */
    public function get() {
        return $this->wrapInBrackets ? "({$this->expression})" : $this->expression;
    }
}