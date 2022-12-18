<?php
declare(strict_types=1);
echo '<?php';
/**
 * @var string $namespace
 * @var string $parentClass
 * @var string $className
 * @var string $tableStructureClassName
 * @var string $recordClassName
 * @var string $tableAlias
 */
?>

declare(strict_types=1);

namespace <?php echo $namespace ?>;

class <?php echo $className ?> extends \<?php echo $parentClass . "\n" ?>
{
    protected function __construct()
    {
        parent::__construct(
            new <?php echo $tableStructureClassName ?>(),
            <?php echo $recordClassName ?>::class,
            '<?php echo $tableAlias ?>'
        );
    }
}