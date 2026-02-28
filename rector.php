<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withRules([
        ReadOnlyClassRector::class
    ])
    ->withSkip([
        // Skip Symfony cache and generated files
        __DIR__.'/var',
        __DIR__.'/migrations',
        __DIR__.'/src/DataFixtures/AppFixtures.php',
        // Skip vendor
        __DIR__.'/vendor',
    ])
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withPhpSets(php85: true)
    ->withSets([
        // Code quality improvements
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
        SetList::EARLY_RETURN,

        // Symfony
        SymfonySetList::SYMFONY_CODE_QUALITY,

        // Doctrine ORM
        DoctrineSetList::DOCTRINE_CODE_QUALITY,

        // PHPUnit 12.x migrations
        PHPUnitSetList::PHPUNIT_100,
        PHPUnitSetList::PHPUNIT_110,
        PHPUnitSetList::PHPUNIT_120,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);
