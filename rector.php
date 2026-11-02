<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__])
    ->withSkip([__DIR__ . '/vendor', __DIR__ . '/Test', __DIR__ . '/rector.php'])
    ->withPhpSets(php83: true);
