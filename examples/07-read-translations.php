<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\TranslationInfo;
use Phore\FileSystem\PhoreDirectory;

/** @return Closure(PhoreDirectory): array<string, TranslationInfo> */
return static function (PhoreDirectory $root): array {
    $site = new SchillerDir($root);
    $page = $site->getPage('/leistungen/diagnostik');
    $translations = $page->getTranslations(); // array<string, TranslationInfo>

    // de: language='de', path='leistungen/diagnostik.md', exists=true, isRootDocument=true
    // en: language='en', path='en/leistungen/diagnostik.md', exists=true, isRootDocument=false
    // fr: language='fr', path='fr/leistungen/diagnostik.md', exists=false, isRootDocument=false
    foreach ($translations as $language => $info) {
        if (!$info->exists) {
            continue; // Kein Body geladen; auch ein Build-Fallback zählt nicht als Datei.
        }
        $document = $page->getTranslation($language); // ?Document
        if ($document !== null) {
            $title = $document->header['title'] ?? null;
            // Für en: 'Diagnostics'.
            assert($document->getTranslation() === $page);
        }
    }

    assert($page->getTranslation() === $page);
    assert($page->getTranslation(null) === $page);
    assert($page->getTranslation('de') === $page);
    assert($page->getTranslation('fr') === null);
    // Verborgene Varianten/Kandidaten fehlen vollständig, keine scheinbar fehlende Datei.
    return $translations;
};
