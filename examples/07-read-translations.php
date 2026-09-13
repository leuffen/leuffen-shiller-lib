<?php

// Verwendet den lesenden $site aus 01.
$page = $site->getPage('/leistungen/diagnostik');
$translations = $page->getTranslations(); // array<string, TranslationInfo>
// de: exists=true,  published=true, path='leistungen/diagnostik.md'
// en: exists=true,  published=true, path='en/leistungen/diagnostik.md'
// fr: exists=false, published=null, path='fr/leistungen/diagnostik.md'

$english = $page->getTranslation('en'); // gespeichertes Document
$french = $page->getTranslation('fr');  // null
// default_lang: de — alle drei Aufrufe liefern dieselbe Stammdokument-Instanz.
$original = $english->getTranslation();     // identisch zu $page
$original = $english->getTranslation(null); // identisch zu $page
$original = $english->getTranslation('de'); // identisch zu $page
$page->getTranslation('de') === $page;     // true: Original verweist auf sich selbst
// de steht hier für die konfigurierte Standardsprache, nicht für einen festen Sonderfall.
// Nicht lesbare Varianten fehlen ganz im Listing; nicht als exists=false tarnen.
