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
    $url = $site->page('leistungen/diagnostik.md')->url('en'); // PageUrl
    // targetPath='/praxis/en/diagnostics/'
    // absoluteUrl='https://example.org/praxis/en/diagnostics/'

    foreach ([
        'https://example.org/praxis/en/diagnostics/?campaign=mail#details',
        '//example.org/praxis/en/diagnostics/',
        'example.org/praxis/en/diagnostics/',
        '/praxis/en/diagnostics/',
        'praxis/en/diagnostics/',
    ] as $input) {
        $result = $site->resolveUrl($input); // UrlLookupResult
        if ($result->status !== UrlMatchStatus::Matched) {
            continue; // not_found, ambiguous oder unsupported: kein Quellpfad erfinden.
        }
        $match = $result->match; // UrlMatch
        // Bei allen obigen Eingaben: path='en/diagnostics.md',
        // requestedLanguage='en', sourceLanguage='en', isFallback=false.
    }

    // Vor Ausführung von Schreibbeispiel 08: fr hat noch keine eigene Datei.
    $fallback = $site->resolveUrl('/praxis/fr/diagnostik/');
    // status='matched', match->path='leistungen/diagnostik.md',
    // requestedLanguage='fr', sourceLanguage='de', isFallback=true.
    // Nicht versehentlich die deutsche Quelle als französischen Inhalt speichern.
    // Domainlose Pfade funktionieren auch ohne config.url; absoluteUrl ist dann null.
    return $fallback;
};
