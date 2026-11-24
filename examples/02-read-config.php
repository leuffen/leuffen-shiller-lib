<?php

// Verwendet den lesenden $site aus 01.
$config = $site->config();
$config->adapter;         // ['id' => 'jekyll-polyglot', 'version' => '1.0']
$config->languages;       // ['de', 'en', 'fr']
$config->defaultLanguage; // 'de'
$config->languageLabels;  // ['de' => 'de', 'en' => 'en', 'fr' => 'fr']
$config->url;             // 'https://example.org'
$config->baseurl;         // ''

// Ergänzung in .shiller.yml: language_labels: {de: Deutsch, en: English, fr: Français}
// Beim nächsten config()-Aufruf erscheinen diese Namen. Keine manuelle YAML-Abfrage.
