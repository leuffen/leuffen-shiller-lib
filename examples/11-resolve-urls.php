<?php

// Verwendet den lesenden $site aus 01.
$english = $site->getPage('/leistungen/diagnostik', 'en');
$english->getUrl();               // '/en/leistungen/diagnostik.html'
$english->getUrl(absolute: true); // 'https://example.org/en/leistungen/diagnostik.html'

$document = $site->getDocumentByUrl(
    'https://user@example.org:8443/en/leistungen/diagnostik.html?preview=1#details',
);
$document === $english; // true: ID '/leistungen/diagnostik', Sprache 'en'
// Gleichwertige Eingaben: /en/leistungen/diagnostik.html, en/leistungen/diagnostik.html,
// example.org/en/leistungen/diagnostik.html; Authority/Query/Fragment werden entfernt.

try {
    $site->getDocumentByUrl('/en/leistungen/diagnostk.html');
} catch (UrlNotResolvableException $error) {
    $error->normalizedPath; // '/en/leistungen/diagnostk.html'
    $error->reason;         // 'not_found'
    $suggestions = $error->suggestions; // ['/en/leistungen/diagnostik.html']
    // UI kann Vorschläge anbieten; keine automatische Auswahl.
}

$fallback = $site->getDocumentByUrl('/fr/leistungen/diagnostik.html');
$fallback->language; // 'de': tatsächliche Quelle der bekannten Fallbackroute
// Ein save an diesem Document würde Deutsch bearbeiten. Für Französisch Beispiel 08 nutzen.
