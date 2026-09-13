<?php

// Verwendet den lesenden $site aus 01.
$page = $site->getPage('/leistungen/diagnostik');
$translations = $page->getTranslations(); // array<string, TranslationInfo>
// de: exists=true,  published=true, path='leistungen/diagnostik.md'
// en: exists=true,  published=true, path='en/leistungen/diagnostik.md'
// fr: exists=false, published=null, path='fr/leistungen/diagnostik.md'

$english = $page->getTranslation('en'); // gespeichertes Document
$french = $page->getTranslation('fr');  // null
$original = $english->getTranslation(); // identisch zu $page; null als Argument bedeutet dasselbe
// Nicht lesbare Varianten fehlen ganz im Listing; nicht als exists=false tarnen.
