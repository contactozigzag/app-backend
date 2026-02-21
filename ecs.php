<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use PhpCsFixer\Fixer\Strict\StrictParamFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/var',
        __DIR__.'/migrations',
        __DIR__.'/src/DataFixtures/AppFixtures.php',
        __DIR__.'/vendor',
    ])
    ->withSets([
        // PSR-12 as the base standard
        SetList::PSR_12,
        // Strict type enforcement helpers
        SetList::STRICT,
        // Array and namespace tidying
        SetList::ARRAY,
        SetList::NAMESPACES,
        SetList::SPACES,
        SetList::DOCBLOCK,
    ])
    ->withRules([
        // Enforce short array syntax: array() → []
        ArraySyntaxFixer::class,
        // Remove unused use statements
        NoUnusedImportsFixer::class,
        // Strict comparison: == → ===, != → !==
        StrictComparisonFixer::class,
        // Enforce strict_types=1 declaration
        DeclareStrictTypesFixer::class,
    ])
    ->withConfiguredRule(OrderedImportsFixer::class, [
        'imports_order' => ['class', 'function', 'const'],
        'sort_algorithm' => 'alpha',
    ]);
