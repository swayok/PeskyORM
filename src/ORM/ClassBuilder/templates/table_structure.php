<?php
declare(strict_types=1);
echo '<?php';
/**
 * @var string $namespace
 * @var string $parentClass
 * @var string $className
 * @var string $tableSchema
 * @var string $tableName
 * @var array $columns
 * @var array $includes
 */
?>

declare(strict_types=1);

namespace <?php echo $namespace ?>;

<?php foreach ($includes as $includeClass): ?>
use <?php echo $includeClass ?>;
<?php endforeach; ?>

class <?php echo $className ?> extends <?php echo $parentClass . "\n" ?>
{
    public function getTableName(): string
    {
        return '<?php echo $tableName ?>';
    }
<?php if (!empty($tableSchema)): ?>

    public function getSchema(): string
    {
        return '<?php echo $tableSchema ?>';
    }
<?php endif; ?>

    protected function registerColumns(): void
    {
<?php foreach ($columns as $columnInfo): ?>
        $this->addColumn(
            (new <?php echo $columnInfo['class'] ?>('<?php echo $columnInfo['name'] ?>'))
<?php foreach ($columnInfo['addons'] as $addon):?>
                -><?php echo $addon['name']; ?>(<?php echo implode(', ', $addon['arguments'] ?? []); ?>)
<?php endforeach; ?>
        );
<?php endforeach; ?>
    }

    protected function registerRelations(): void
    {
    }
}