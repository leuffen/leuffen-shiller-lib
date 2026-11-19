# Polyglot Editable Headers

| Datum | Benutzername | Kurzbeschreibung |
|---|---|---|
| 2026-11-19 | dermatthes | §§ 1–5: Polyglot-Entwurf und API angelegt |

## § 1 Ziel

Eine Page liefert über `getEditableHeaders()` eine geordnete Liste der
tatsächlich für sie sichtbaren, konfigurierten Formularfelder. Die Liste
enthält den aktuellen Wert sowie die Metadaten für den Formularaufbau.
Die API verändert keine Seiten und schreibt keine Defaults in den Header.

## § 2 Anwendung

```php
$page = $site->getPage('/leistungen');
$fields = $page->getEditableHeaders();
foreach ($fields as $field) {
    echo $field['name'] . ': ' . json_encode($field['value']) . PHP_EOL;
}
```

Ein Feld liefert `name`, `type`, `label`, `description`,
`allowNull`, `allowEmpty`, `regex`, `options`, `default`,
`value`, `present` und `order`. `present=false` bedeutet, dass
der tatsächliche Headerwert fehlt und `value` nur ein Anzeige-Default
ist. Ohne Konfigurationsdatei ist das Ergebnis `[]`.

## § 3 Website-Konfiguration

Die Konfiguration liegt im Document Root unter
`editable-headers.conf.yaml` und wird vom Polyglot-Adapter gelesen:

```yaml
fields:
  - name: title
    type: string
    label: Titel
    description: Überschrift der Seite
    order: 10
    allowNull: false
    allowEmpty: false
    regex: '~^.{1,100}$~'

  - name: published
    type: switch
    label: Veröffentlicht
    default: false
    order: 20

  - name: teaser
    type: textarea
    label: Kurzbeschreibung
    when:
      include: ['~^leistungen/.*\\.md$~', '~^leistungen\\.md$~']
      exclude: '~^leistungen/intern/~'
      on:
        category: [praxis, news]
      not:
        hidden: true

  - name: priority
    type: integer
    label: Priorität

  - name: layout
    type: select
    label: Layout
    options: [default, landing]

  - name: tags
    type: multiselect
    label: Tags
    options: [praxis, news, service]
```

Unterstützte Typen: `switch` (Boolean), `string` (einzeilig),
`textarea` (mehrzeilig), `integer`, `select` und `multiselect`.
Die Option `order` ist optional; ohne sie gilt die YAML-Position.
Gleiche Order-Werte bleiben in der ursprünglichen Reihenfolge.
Reguläre Ausdrücke sind inklusive PCRE-Delimiter anzugeben.

## § 4 Bedingungen und Semantik

Ein definiertes Feld ist standardmäßig sichtbar. `when.include` und
`when.exclude` prüfen den tatsächlichen Quellpfad relativ zum Document
Root mit PCRE-Regex. Eine Pattern-Liste ist ODER-verknüpft.
`include` verlangt mindestens einen Treffer; `exclude` verbietet
jeden Treffer. `when.on` und `when.not` prüfen die tatsächlichen
Header-Werte mit strengem Typvergleich. Innerhalb einer Werteliste gilt
ODER, zwischen Schlüsseln und Bedingungsarten UND. `on` verlangt ein
existierendes Feld mit passendem Wert; `not` ist bei fehlendem Feld
erfüllt. `false`, `null` und nicht vorhandene Keys unterscheiden sich.

Nur die Sichtbarkeit wird beim Lesen ausgewertet. `regex`,
`allowNull`, `allowEmpty` und Options sind Form-Metadaten, keine
automatische Validierung bei `save()`. Ein Consumer darf eine
Formularantwort nicht ungeprüft als vollständigen Header ersetzen.
Ungültige Konfiguration wird als ValidationException gemeldet.

## § 5 Scope und Ausblick

Der neue Adapter-Aufruf ist für Jekyll Polyglot implementiert.
Der gemeinsame Adapter-Default und damit Legacy liefert vorerst `[]`.
Eine spätere Legacy-Erweiterung kann eigene Konfigurationsquellen
und Pfadregeln implementieren, ohne `Document` zu ändern.
