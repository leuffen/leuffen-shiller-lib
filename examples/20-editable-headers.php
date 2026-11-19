<?php
use Leuffen\Shiller\Access\Adapter\Adapter;
use Leuffen\Shiller\Access\DTO\Document;
use Leuffen\Shiller\Access\ShillerRepository;

// Fortsetzung von Beispiel 01: $site ist ein ShillerRepository mit Polyglot-Adapter.
// Die YAML aus 20-editable-headers.conf.yaml liegt im Document Root unter
// editable-headers.conf.yaml, z. B. /srv/site/docs/editable-headers.conf.yaml.
// Ausgangsseite: docs/leistungen/diagnostik.md (siehe examples/README.md).

$page = $site->getPage('/leistungen/diagnostik');
$fields = $page->getEditableHeaders();

// Die Felder sind nach order sortiert und enthalten aktuelle Werte bzw. Defaults.
// Der Beispiel-Header hat title=Diagnostik, published=true und kein layout.
foreach ($fields as $field) {
    echo sprintf(
        "%s (%s): %s%s\n",
        $field['label'],
        $field['type'],
        json_encode($field['value'], JSON_UNESCAPED_UNICODE),
        $field['present'] ? '' : ' [Default/nicht gesetzt]',
    );
}

// Erwartete Ausschnitte: title = "Diagnostik", published = true,
// layout = "default" [Default/nicht gesetzt], featured = false
// [Default/nicht gesetzt]. Das bedingte Feld featured ist nur sichtbar,
// wenn published=true ist und hidden nicht true ist.
$labels = array_column($fields, 'label', 'name');
echo $labels['title']; // Seitentitel

// Die Formdaten enthalten auch description, allowNull, allowEmpty,
// regex, options, default, value, present, order und type.
// Diese Metadaten sind nur eine Lese-/Formularbeschreibung: kein save(),
// keine automatische Feldvalidierung und kein Rückschreiben von Defaults.
