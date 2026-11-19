<?php

use Leuffen\Shiller\Access\DTO\Document;

// Verwendet den schreibenden $site aus 00; Definitionen: README / Proposal § 7.
$page = $site->getPage('/leistungen/diagnostik');
$fields = $page->getHeaderDefinitions();
foreach ($fields->fields as $field) {
    echo $field->key . ': ' . $field->type . PHP_EOL;
}
// FieldDefinition liefert key, label, type, options und presentation.

// Vor dem Öffnen eines Neuanlageformulars: erzeugt noch kein Document.
$newFields = $site->getHeaderDefinitions('/leistungen/vorsorge');
$actions = $site->capabilities('/leistungen/vorsorge');
$actions->create; // true: Seite kann angelegt werden

$existingActions = $site->capabilities($page->id);
$existingActions->write;  // true; save prüft erneut
$existingActions->rename; // true bewertet die Quelle, kein noch unbekanntes Ziel
// Der Formrenderer verwendet optional presentation.widget, z.B. textarea.
// Unbekannte eigene Header-Schlüssel brauchen keine Definition.
