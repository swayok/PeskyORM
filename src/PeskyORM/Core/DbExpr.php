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
class DbExpr
{
    
    protected $expression = '';
    /**
     * @var bool|null
     */
    protected $wrapInBrackets = null;
    protected $allowValidation = true;
    
    /**
     * @param string $expression
     * @param bool|null $wrapInBrackets - true: wrap expression in round brackets; null: autodetect;
     * @return DbExpr
     */
    static public function create($expression, $wrapInBrackets = null)
    {
        return new DbExpr($expression, $wrapInBrackets);
    }
    
    /**
     * DbExpr constructor.
     * @param bool|null $wrapInBrackets - true: wrap expression in round brackets; null: autodetect;
     * @param string $expression
     */
    public function __construct($expression, $wrapInBrackets = null)
    {
        $this->expression = $expression;
        $this->wrapInBrackets = $wrapInBrackets;
    }
    
    /**
     * @param bool $wrapInBrackets
     * @return $this
     */
    public function setWrapInBrackets($wrapInBrackets)
    {
        $this->wrapInBrackets = $wrapInBrackets;
        return $this;
    }
    
    /**
     * @return $this
     */
    public function configureBracketsForColumn()
    {
        if ($this->wrapInBrackets === null) {
            // Wrap in brackets only if expression is "SELECT ..."
            $this->wrapInBrackets = preg_match('%^\s*SELECT\s%i', $this->expression) > 0;
        }
        return $this;
    }
    
    /**
     * @return string
     */
    public function get()
    {
        return $this->shouldWrapInBrackets() ? "({$this->expression})" : $this->expression;
    }
    
    /**
     * @return bool
     */
    protected function shouldWrapInBrackets()
    {
        if ($this->wrapInBrackets === null) {
            if (preg_match(
                '%^\s*(SELECT|INSERT|WITH|UPDATE|DELETE|DROP|ALTER|ORDER|GROUP|HAVING|LIMIT|OFFSET|WHERE|CREATE)\s%i',
                $this->expression
            )) {
                // It is expected to be a DB query or part of it
                // so wrapping here is not required by default
                return false;
            }
            return true;
        }
        return $this->wrapInBrackets;
    }
    
    /**
     * Disable relation and column name validation when DbExpr is used by OrmSelect
     * @return $this
     */
    public function noValidate()
    {
        $this->allowValidation = false;
        return $this;
    }
    
    /**
     * @return bool
     */
    public function isValidationAllowed()
    {
        return $this->allowValidation;
    }
    
    /**
     * @param array $replaces - associative array where keys are regular expressions and values are replacements
     * @return static - new record
     */
    public function applyReplaces(array $replaces)
    {
        return new static(preg_replace(array_keys($replaces), array_values($replaces), $this->expression), $this->wrapInBrackets);
    }
}