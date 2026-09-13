<?php

// Verwendet den schreibenden $site aus 00; Definitionen: README / Proposal § 7.
$page = $site->getPage('/leistungen/diagnostik');
$fields = $page->getHeaderDefinitions();
$fields->get('short_title')->maxLength; // 60
$fields->get('published')->type->value; // 'boolean'
$fields->get('layout')->options;       // default=Standard, landing=Landingpage

// Vor dem Öffnen eines Neuanlageformulars: erzeugt noch kein Document.
$newFields = $site->getHeaderDefinitions('/leistungen/vorsorge');
$actions = $site->capabilities('/leistungen/vorsorge');
$actions->createFile; // true in der Basisfixture

$existingActions = $site->capabilities($page->id);
$existingActions->write;  // true; save prüft erneut
$existingActions->rename; // true bewertet die Quelle, kein noch unbekanntes Ziel
// Der Formrenderer verwendet optional presentation.widget, z.B. textarea.
// Unbekannte eigene Header-Schlüssel brauchen keine Definition.
