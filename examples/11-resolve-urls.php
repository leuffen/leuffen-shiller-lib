<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\Document;
use Leuffen\Schiller\UrlNotResolvableException;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): Document {
    $site = new SchillerDir($root);
    $page = $site->getPage('leistungen/diagnostik.md');
    $english = $page->getTranslation('en'); // In der Fixture vorhanden.

    // $page->getUrl() === '/leistungen/diagnostik.html'
    // $english->getUrl() === '/en/leistungen/diagnostik.html'
    // $english->getUrl(absolute: true) ===
    // 'https://example.org/en/leistungen/diagnostik.html'
    // Kein Sprachparameter: Die Sprache gehört zum Dokument.

    foreach ([
        'https://user@example.org:8443/en/leistungen/diagnostik.html?preview=1#details',
        '//example.org/en/leistungen/diagnostik.html',
        'example.org/en/leistungen/diagnostik.html',
        'https://other.example/en/leistungen/diagnostik.html',
        '/en/leistungen/diagnostik.html',
        'en/leistungen/diagnostik.html',
    ] as $input) {
        $document = $site->getDocumentByUrl($input); // Document oder Exception
        // Immer path='en/leistungen/diagnostik.md', language='en'.
        assert($document === $english);
        assert($document->getRootDocument() === $page);
    }

    try {
        $site->getDocumentByUrl('/en/leistungen/diagnostk.html');
    } catch (UrlNotResolvableException $error) {
        // normalizedPath='/en/leistungen/diagnostk.html', reason='not_found'
        // suggestions=['/en/leistungen/diagnostik.html']; hints erläutert den Mismatch.
        // Alle Diagnosen sind bereinigt; keine Credentials oder verborgenen URLs.
        $suggestions = $error->suggestions;
    }

    // Keine französische Datei in der Ausgangsfixture: bekannte Polyglot-Fallbackroute.
    $fallback = $site->getDocumentByUrl('/fr/leistungen/diagnostik.html');
    assert($fallback === $page); // Tatsächliche deutsche Quelle, kein fr-Dokument.
    // language='de'; getUrl()='/leistungen/diagnostik.html'.
    // Ohne config.url funktioniert die lokale Auflösung genauso.
    return $english;
};
