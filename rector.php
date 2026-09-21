<?php declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/spec',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkipPath(__DIR__.'/tests/Application/config/bundles.php')
    ->withSkipPath(__DIR__.'/tests/Application/var')
    ->withImportNames(importShortClasses: false)
    ->withPHPStanConfigs([
        __DIR__.'/vendor/phpstan/phpstan-doctrine/extension.neon',
        __DIR__.'/vendor/phpstan/phpstan-webmozart-assert/extension.neon',
        __DIR__.'/phpstan.neon',
    ])
    ->withComposerBased(phpunit: true)
    ->withPhpSets()
    ->withPreparedSets(codeQuality: true, phpunitCodeQuality: true);
