<?php
declare(strict_types=1);

// Generate Magento factories and extension attribute classes on demand, as Magento's own unit test bootstrap does
use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesInterfaceGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;

require __DIR__ . '/../../vendor/autoload.php';

$generator = new GeneratedClassesAutoloader(
    [new ExtensionAttributesGenerator(), new ExtensionAttributesInterfaceGenerator(), new FactoryGenerator()],
    new Io(new File(), sys_get_temp_dir() . '/trueloyal-unit-generated')
);
spl_autoload_register([$generator, 'load']);
