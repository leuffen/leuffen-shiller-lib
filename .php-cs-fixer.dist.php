<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

/**
 * Formatter-Konfiguration für die Schiller-Library.
 *
 * PHP-CS-Fixer prüft produktiven Code, Tests und die dokumentierten Beispiele
 * gegen den aktuellen PHP-FIG PER Coding Style. Fachliche Kommentare und
 * API-Dokumentation werden zusätzlich im Review geprüft.
 */
$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/test',
        __DIR__ . '/examples',
    ])
    ->name('*.php')
    ->exclude([
        'vendor',
    ]);

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS' => true,
        'no_multiple_statements_per_line' => true,
    ])
    ->setFinder($finder);
