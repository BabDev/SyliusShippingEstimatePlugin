<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\ClassMethod\ReplaceTestAnnotationWithPrefixedFunctionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/spec',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkipPath(__DIR__.'/tests/Application/config/bundles.php')
    ->withSkipPath(__DIR__.'/tests/Application/node_modules')
    ->withSkipPath(__DIR__.'/tests/Application/var')
    ->withSkip([
        ReplaceTestAnnotationWithPrefixedFunctionRector::class,
    ])
    ->withImportNames(importShortClasses: false)
    ->withPHPStanConfigs([
        __DIR__.'/vendor/phpstan/phpstan-doctrine/extension.neon',
        __DIR__.'/vendor/phpstan/phpstan-webmozart-assert/extension.neon',
        __DIR__.'/phpstan.neon',
    ])
    ->withComposerBased(phpunit: true)
    ->withPhpSets()
    ->withPreparedSets(codeQuality: true, phpunitCodeQuality: true);
