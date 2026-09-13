<?php

// Verwendet den lesenden $site aus 01.
$config = $site->config();
$config->languages;       // ['de', 'en', 'fr']
$config->defaultLanguage; // 'de'
$config->languageLabels;  // ['de' => 'de', 'en' => 'en', 'fr' => 'fr']
$config->url;             // 'https://example.org'
$config->baseurl;         // ''

// Ergänzung in schiller.yaml: language_labels: {de: Deutsch, en: English, fr: Français}
// Beim nächsten config()-Aufruf erscheinen diese Namen. Keine manuelle YAML-Abfrage.
