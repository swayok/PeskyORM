<?php
declare(strict_types=1);
echo '<?php';
/**
 * @var string $namespace
 * @var string $parentClass
 * @var string $className
 * @var string $tableClassName
 * @var array $includes
 * @var array $properties
 * @var array $setters
 */
?>

declare(strict_types=1);

namespace <?php echo $namespace ?>;

<?php
    foreach ($includes as $class) {
        echo "use {$class};\n";
    }
?>
/**
<?php
    foreach ($properties as $name => $type) {
        $type = str_pad($type, 11, ' ', STR_PAD_RIGHT);
        echo " * @property {$type} \${$name}\n";
    }
    echo "\n";
    foreach ($setters as $name) {
        echo " * @method \$this \${$name}(mixed \$value, bool \$isFromDb = false)\n";
    }
?>
 */
class <?php echo $className ?> extends \<?php echo $parentClass . "\n" ?>
{
    protected function __construct()
    {
        parent::__construct(<?php echo $tableClassName ?>::getInstance());
    }
}