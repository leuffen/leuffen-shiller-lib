<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

/**
 * Formatter-Konfiguration für den Entwurfs-PR.
 *
 * Die Beispielreihe wird gegen den aktuellen PHP-FIG PER Coding Style geprüft.
 * Semantische Kommentar- und API-Dokumentationsregeln bleiben zusätzlich Teil
 * des Reviews, da sie nicht zuverlässig durch einen Formatter erzwungen werden.
 */
$finder = Finder::create()
    ->in(__DIR__ . '/examples')
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS' => true,
        'no_multiple_statements_per_line' => true,
    ])
    ->setFinder($finder);
