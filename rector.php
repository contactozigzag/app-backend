<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
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
    ->withSkip([
        // Skip Symfony cache and generated files
        __DIR__.'/var',
        __DIR__.'/migrations',
        __DIR__.'/src/DataFixtures/AppFixtures.php',
        // Skip vendor
        __DIR__.'/vendor',
    ])
    ->withPhpSets()
    ->withSets([
        // PHP 8.4 upgrades (covers all PHP 8.0–8.4 migrations)
        LevelSetList::UP_TO_PHP_84,

        // Code quality improvements
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
        SetList::EARLY_RETURN,

        // Symfony — all sets up to 8.0
        SymfonySetList::SYMFONY_60,
        SymfonySetList::SYMFONY_61,
        SymfonySetList::SYMFONY_62,
        SymfonySetList::SYMFONY_63,
        SymfonySetList::SYMFONY_64,
        SymfonySetList::SYMFONY_70,
        SymfonySetList::SYMFONY_71,
        SymfonySetList::SYMFONY_72,
        SymfonySetList::SYMFONY_73,
        SymfonySetList::SYMFONY_74,
        SymfonySetList::SYMFONY_80,
        SymfonySetList::SYMFONY_CODE_QUALITY,

        // Doctrine ORM 3.x migrations
        DoctrineSetList::DOCTRINE_ORM_25,
        DoctrineSetList::DOCTRINE_ORM_28,
        DoctrineSetList::DOCTRINE_ORM_213,
        DoctrineSetList::DOCTRINE_ORM_214,
        DoctrineSetList::DOCTRINE_ORM_219,
        DoctrineSetList::DOCTRINE_ORM_300,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,

        // PHPUnit 12.x migrations
        PHPUnitSetList::PHPUNIT_100,
        PHPUnitSetList::PHPUNIT_110,
        PHPUnitSetList::PHPUNIT_120,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);
