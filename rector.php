<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withComposerBased(phpunit: true, symfony: true, laravel: true)
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/examples',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php83: true)
    ->withTypeCoverageLevel(50)
    ->withDeadCodeLevel(51)
    ->withCodeQualityLevel(73);
