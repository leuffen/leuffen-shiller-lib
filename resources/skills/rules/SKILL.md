---
name: shiller-rules
description: Anlegen, Bearbeiten und Prüfen von Shiller-Rules unter dem Document Root in _rules.d. Verwende diesen Skill, wenn Rules, ihre Selector, Event-Filter, Prioritäten oder das Einlesen und Parsen durch leuffen-shiller-lib erklärt, erweitert oder überprüft werden sollen.
---

# Shiller-Rules bearbeiten und verstehen

## Speicherort und Geltungsbereich

Shiller liest Rules aus `<document-root>/_rules.d/`. Die Dateien müssen auf
`.md` enden; `ShillerRuleSetManager` berücksichtigt auch Unterverzeichnisse.
Das Verzeichnis der kopierbaren ThemeJS2-Vorlage liegt beispielsweise unter
`_tpl/_root/docs/_rules.d/` und wird beim Anlegen einer Website nach
`docs/_rules.d/` kopiert. **Rules sind Prompt-Anweisungen, kein ausführbarer
Code.** Shiller wendet sie pro editierbarer Zieldatei und Event an.

Nur Markdown-Dateien (`**/*.md`) sowie `.yml`/`.yaml` unter `_data/` sind
derzeit mit `ShillerContentSelector` editierbar. Dateien in `_rules.d/` sind
ausdrücklich vom Editier-Selector ausgenommen. Eigenständige HTML-Dateien
werden durch eine HTML-Rule nicht automatisch editierbar.

## Frontmatter korrekt schreiben

Jede Rule braucht YAML-Frontmatter mit `selector` als nichtleerem String
oder nichtleerer Liste von Strings. Die Werte dürfen weder leer noch
whitespace-only sein. `on` ist optional und nimmt einen Event-Namen oder
eine Liste entgegen; ohne `on` gilt die Rule für alle Events. `important`
ist optional und muss ein YAML-Boolean sein (`true`/`false`). Danach
folgt der Markdown-Regeltext.

Beispiel einer allgemeinen Rule (Datei `_rules.d/10-general.md`):

```markdown
---
selector:
  - "**/*.md"
  - "_data/**/*.yml"
  - "_data/**/*.yaml"
---
Erhalte die technische Struktur der Zieldatei. Ändere nur belegte Inhalte.
```

Eine speziellere Regel nur für die Startseite:

```markdown
---
selector: "index.md"
"on": "edit"
---
Formuliere den Einstieg anhand des belegten Projektkontexts.
```

Bei Event-Regeln den Schlüssel `"on"` **immer zitieren**. Der installierte
YAML-Parser verwendet YAML 1.1 und kann den unzitierten Schlüssel `on` als
Boolean `true` interpretieren. Schiller unterstützt diesen Sonderfall
zwar ausdrücklich über den Key `1`, das Zitieren verhindert aber
Missverständnisse und bleibt gültiges YAML.

Für einen höheren Vorrang ist beispielsweise erlaubt:

```yaml
selector:
  - "leistungen/**/*.md"
  - "tag:arzt"
"on":
  - edit
  - user-request
important: true
```

Die folgenden Abschnitte zu Selector und Prioritäten bestimmen, **wann**
diese Rule tatsächlich geladen wird.

## Selector und Match-Verhalten

Selector sind relativ zum Document Root, **nicht** relativ zur Rule-Datei:

- `index.md`: exakte Datei im Document Root
- `kontakt/index.md`: exakte Unterseite
- `leistungen/**/*.md`: Markdown direkt in `leistungen/` und in weiteren
  Unterverzeichnissen
- `**/*.md`: Markdown auf jeder Verzeichnisebene
- `_data/**/*.yml`: YAML unter `_data/`, einschließlich tieferer Ebenen
- `tag:arzt`: Markdown mit dem exakten Tag `arzt` im Frontmatter-Feld
  `tags`, `ptags` oder `shiller.tags`

Glob-Selector unterstützen `*`, `**` und `?`; `**/` kann auch **null**
Verzeichnisebenen treffen. Es wird jeweils der vollständige relative
Pfad geprüft. Tags werden nicht auf YAML-Zieldateien angewendet.
Eine Rule mit mehreren Selectoren gilt, sobald **mindestens einer** die
Zieldatei trifft. Lege im Regelfall breite Grundregeln und wenige
spezifische Regeln an, statt Anweisungen unnötig zu duplizieren.

## Parsing und Reihenfolge in Schiller

1. `ShillerRuleSetManager` listet `_rules.d` rekursiv nach Pfad sortiert
   auf und liest bei jeder `.md`-Datei mit
   `PhoreFile::get_front_matter(required: false)` das YAML-Frontmatter.
   Ohne gültiges Frontmatter beziehungsweise ohne gültigen `selector`
   wird eine `RuntimeException` geworfen.
2. Der Manager normalisiert String/Listen für `selector` und `on`,
   entfernt Dubletten, interpretiert `important` als Boolean und speichert
   den Markdown-Body **ohne Frontmatter** als Rule-Content.
3. `getRulesFor($relativeFile, $event)` prüft zunächst das Event:
   Hat die Rule ein `on` und stimmt es nicht exakt mit dem Event überein,
   wird sie vollständig ausgelassen. Das normale
   `shiller ai adjust` verwendet das Event `edit`.
4. `ShillerContentSelector::select($selector)` ermittelt für jeden
   Selector die **aktuell vorhandenen editierbaren Dateien im gesamten
   Document Root**, nicht nur die gerade zum Adjust ausgewählten Dateien.
   Der Manager cached diese Match-Listen pro Selector.
5. Für einen passenden Selector gilt
   `specificity = 1 / Anzahl seiner Treffer`. Eine Rule mit mehreren
   passenden Selectoren erhält den **höchsten** Spezifitätswert.
6. Zuerst kommen **normale** Rules von niedriger zu hoher Specificity,
   dann die Rules mit `important: true` ebenfalls von niedriger zu hoher
   Specificity. Bei gleicher Important-Stufe und gleicher Specificity
   entscheidet der Rule-Dateipfad alphabetisch. Die stärkste Regel
   steht also zuletzt.

Beispiel: Enthält ein Document Root genau `index.md` und
`leistungen/index.md`, trifft `**/*.md` beide Dateien
(Specificity `0.5`), `index.md` hingegen nur die Startseite
(Specificity `1`). Eine passende `important`-Rule steht unabhängig
davon hinter allen normalen Rules.

## Wie die Rules in den AI-Request gelangen

`ShillerContentAction::adapt()` wählt zunächst die Zieldateien aus,
erzeugt dann für **jede Zieldatei separat** einen Request und hängt
folgende Informationen in dieser Reihenfolge an:

1. Den Standard-Skill `resources/skills/adapt-content/SKILL.md`,
   sofern nicht über die CLI ein anderer Basis-Skill gewählt wurde
2. Die konfigurierten Projektkontext-Dateien
3. Den Prioritäts-Prompt `rulePriority` und die passenden Rules als
   `FilePrompt` (`rule1`, `rule2` usw.) mit Selector, Trefferzahl,
   Specificity und Important-Status
4. Optionale `<zieldatei>.d.*`-Sidecars als beschreibende Daten
5. Den Bearbeitungsauftrag und die vollständige `targetFile`

Der Projektkontext bleibt für **Fakten** maßgeblich. Important-Rules
dürfen allgemeine Bearbeitungsanweisungen überstimmen, aber keine
Namen, Adressen oder andere Fakten erfinden. Der Basis-Skill bewahrt
beim normalen Adjust insbesondere das bestehende Markdown-Layout.
Einen Neuaufbau deshalb nur dann anweisen, wenn der Benutzer ihn
ausdrücklich verlangt hat. In `_data`-YAML vorhandene Keys und
Struktur grundsätzlich erhalten; Sidecar-Schemas berücksichtigen.

`getPromptsFor()` fügt den Prioritäts-Prompt nur hinzu, wenn mindestens
eine Rule passt. Änderungen am Rule-Text ändern somit den AI-Prompt
für passende Dateien, nicht automatisch den PHP-Parser.

## Workflow für neue und geänderte Rules

1. Prüfe das tatsächliche Document Root und die vorhandenen Dateien
   unter `_rules.d/`. Passe eine passende Rule an, statt eine zweite
   Regel mit identischem Geltungsbereich anzulegen.
2. Bestimme **zuerst** die Ziel-Dateitypen, Pfade und optional Tags.
   Verwende den engsten passenden Selector. Global geltende
   Vorgaben in eine allgemeine Rule, seitenspezifische in eine
   passend benannte Rule schreiben.
3. Verwende `"on"` nur für echten Event-spezifischen Inhalt und
   `important: true` nur für begründete, zwingende Überschreibungen.
   Den Standardfall ohne `important` belassen.
4. Regeltext imperativ, konkret und überprüfbar halten; bestehende
   technische Struktur und Faktenquellen respektieren. Inline-Code
   in einfachen Backticks, **Codebeispiele in Markdown ausschließlich
   mit einem eigenen öffnenden und schließenden Triple-Backtick-Fence**
   formatieren; optional eine Sprachkennung wie `yaml`, `php` oder
   `bash` setzen. Keine vierfach eingerückten Codeblöcke benutzen.
5. Frontmatter-Felder auf Typ und nichtleere Werte prüfen; passende
   sowie **nicht** passende Zieldateien und Events gegentesten.
   Die Reihenfolge der angewandten Rules zusätzlich prüfen.
6. Den tatsächlichen Diff prüfen und nur beauftragte Rule-Dateien
   beziehungsweise ausdrücklich vereinbarte Parser-Dateien ändern.

Der Manager kann ohne AI-Aufruf verwendet werden, um die Auswahl
nachzuvollziehen:

```php
use Leuffen\Shiller\Automation\ShillerRuleSetManager;

$rules = (new ShillerRuleSetManager('/srv/site/docs'))
    ->getRulesFor('index.md', 'edit');

foreach ($rules as $rule) {
    echo $rule->source . ': ' . $rule->selector
        . ' (' . $rule->matchCount . ' Treffer)' . PHP_EOL;
}
```

Beim tatsächlichen Adjust zeigt `--debug` die Rule-Reihenfolge,
Selector, Match-Anzahl und Specificity vor den AI-Requests:

```bash
shiller ai --event user-request --debug adjust index.md
```

**Achtung:** `--debug` ist **kein Dry-Run**. Der Befehl ruft die KI auf
und kann die ausgewählten Inhalte verändern. Für eine reine
Rule-Prüfung den Manager wie oben ohne `adapt()` aufrufen.
Die bestehenden Tests liegen unter `test/ShillerRuleSetManagerTest.php`
und `test/ShillerContentSelectorTest.php`.

## Quellen im Repository

- [Rule-Parser und Priorität](../../../src/Automation/ShillerRuleSetManager.php)
- [Zieldatei- und Tag-Selector](../../../src/Automation/ShillerContentSelector.php)
- [AI-Prompt-Zusammenstellung](../../../src/Automation/ShillerContentAction.php)
- [CLI-Parameter](../../../src/Automation/ShillerCli.php)
- [Vorhandener Adapt-Basis-Skill](../adapt-content/SKILL.md)
- [Rules-Demo](../../../demo/project/docs/_rules.d/10-general.md)
