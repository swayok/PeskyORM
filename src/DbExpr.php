<?php

declare(strict_types=1);

namespace PeskyORM;

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
    
    protected string $expression = '';
    protected bool $wrapInBrackets = true;
    protected bool $allowValidation = true;
    
    public static function create(string $expression, ?bool $wrapInBrackets = null): DbExpr
    {
        return new DbExpr($expression, $wrapInBrackets);
    }
    
    /**
     * @param bool|null $wrapInBrackets - true: wrap expression in round brackets; null: autodetect;
     * @param string $expression
     */
    public function __construct(string $expression, ?bool $wrapInBrackets = null)
    {
        $this->expression = $expression;
        if ($wrapInBrackets === null) {
            $wrapInBrackets = !preg_match(
                '%^\s*(SELECT|INSERT|WITH|UPDATE|DELETE|DROP|ALTER|ORDER|GROUP|HAVING|LIMIT|OFFSET|WHERE|CREATE)\s%i',
                $expression
            );
        }
        $this->setWrapInBrackets($wrapInBrackets);
    }
    
    public function setWrapInBrackets(bool $wrapInBrackets): DbExpr
    {
        $this->wrapInBrackets = $wrapInBrackets;
        return $this;
    }
    
    public function get(): string
    {
        return $this->wrapInBrackets ? "({$this->expression})" : $this->expression;
    }
    
    /**
     * Disable relation and column name validation when DbExpr is used by OrmSelect
     */
    public function noValidate(): DbExpr
    {
        $this->allowValidation = false;
        return $this;
    }
    
    public function isValidationAllowed(): bool
    {
        return $this->allowValidation;
    }
    
    /**
     * @param array $replaces - associative array where keys are regular expressions and values are replacements
     * @return DbExpr - new object
     */
    public function applyReplaces(array $replaces): DbExpr
    {
        return new static(preg_replace(array_keys($replaces), array_values($replaces), $this->expression), $this->wrapInBrackets);
    }
}