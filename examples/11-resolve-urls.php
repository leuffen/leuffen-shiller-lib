<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\UrlLookupResult;
use Leuffen\Schiller\UrlMatchStatus;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): UrlLookupResult {
    $site = new SchillerDir($root);
    $home = $site->page('index.md')->url(); // targetPath='/'
    $englishHome = $site->resolveUrl('/en/'); // match->path='en/index.md'
    // Beide Startseiten haben keinen Permalink.
    $url = $site->page('leistungen/diagnostik.md')->url('en'); // PageUrl
    // targetPath='/en/leistungen/diagnostik.html'
    // absoluteUrl='https://example.org/en/leistungen/diagnostik.html'

    foreach ([
        'https://example.org/en/leistungen/diagnostik.html?campaign=mail#details',
        '//example.org/en/leistungen/diagnostik.html',
        'example.org/en/leistungen/diagnostik.html',
        '/en/leistungen/diagnostik.html',
        'en/leistungen/diagnostik.html',
    ] as $input) {
        $result = $site->resolveUrl($input); // UrlLookupResult
        if ($result->status !== UrlMatchStatus::Matched) {
            continue; // not_found, ambiguous oder unsupported: kein Quellpfad erfinden.
        }
        $match = $result->match; // UrlMatch
        // Bei allen obigen Eingaben: path='en/leistungen/diagnostik.md',
        // requestedLanguage='en', sourceLanguage='en', isFallback=false.
    }

    // Vor Ausführung von Schreibbeispiel 08: fr hat noch keine eigene Datei.
    $fallback = $site->resolveUrl('/fr/leistungen/diagnostik.html');
    // status='matched', match->path='leistungen/diagnostik.md',
    // requestedLanguage='fr', sourceLanguage='de', isFallback=true.
    // Nicht versehentlich die deutsche Quelle als französischen Inhalt speichern.
    // Domainlose Pfade funktionieren auch ohne config.url; absoluteUrl ist dann null.
    return $fallback;
};
