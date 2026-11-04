# Shiller Library

## CLI: Neue Website anlegen

Vor dem ersten Shiller-Aufruf muss im neuen Website-Projekt das
PHP-/Composer-Paket `leuffen/shiller-lib` installiert sein
(`./vendor/bin/shiller` ist dann vorhanden). Das ThemeJS2-Paket
muss unter `node_modules/@leuffen/themejs2/` liegen;
es kann per npm bereitgestellt oder bewusst im Website-Repository
mitversioniert werden. Die Befehle werden **im Website-Projektverzeichnis**
ausgeführt, nicht im Theme-Paket.

```sh
# 1. Neues Website-Projektverzeichnis vorbereiten und hineinwechseln.
mkdir meine-website
cd meine-website

# 2. Nach Bereitstellung von Composer-CLI und Theme: Startstruktur kopieren.
./vendor/bin/shiller init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags base

# 3. Optional: zusätzliche Theme-Variante installieren.
./vendor/bin/shiller install --tags "theme:osman"

# 4. Projektfakten in .shiller-context.d/project.md eintragen.
#    Nur bei neuen Rohdaten in .shiller-context.d/raw/ zusätzlich:
./vendor/bin/shiller context build

# 5. Installierte Startseite aus dem Projektkontext anpassen.
./vendor/bin/shiller adapt --select index.md
```

Nach `init` liegen die Website-Dateien in `docs/`
(Standard-Document-Root). `_tpl/_root/docs/` wird dorthin kopiert;
andere Dateien aus `_tpl/_root/` gehen in die Projektwurzel.
`_tpl/_root/.shiller.yml` wird so zur Projektkonfiguration
`.shiller.yml` mit `template_dir` und `skills_dir`.
Die anderen Dateien unter `_tpl/` werden anhand ihrer
`shiller.tags` installiert; `skills/` bleibt im npm-Paket.
Die Reihenfolge lautet: **init → optional install → Kontext pflegen
bzw. context build → adapt**. Installationsbefehle überschreiben
vorhandene Zielinhalte, daher Varianten vor der AI-Anpassung wählen.

## CLI: Eine bestehende Datei bearbeiten

Bei bereits initialisierter Website aus der Projektwurzel ausführen:

```sh
# Eine Datei relativ zu docs/ bearbeiten:
./vendor/bin/shiller adapt --select index.md

# Mehrere Dateien aus derselben Website in einem Lauf:
./vendor/bin/shiller adapt --select "index.md,_data/general.yml"

# Eine Datei oder eine Template-Tag-Gruppe auswählen:
./vendor/bin/shiller adapt --select "tag:base"

# Eine geänderte Datei auf die installierte Vorlage zurücksetzen:
./vendor/bin/shiller revert index.md
```

`adapt` nutzt die Projektfakten aus
`.shiller-context.d/project.md` und die Skills des Theme-Pakets.
Mit `--root public` kann der Document Root statt `docs/`
`public/` sein; der Selektor bleibt relativ zu diesem Verzeichnis.
`revert` verwirft individuelle Änderungen an der ausgewählten
Datei und ist deshalb nur bewusst zu verwenden.

Damit die Theme-Vorlagen und Skills **auch ohne erneutes `npm install`**
bereitstehen, kann das Website-Repository ausschließlich
`node_modules/@leuffen/themejs2/` vom sonst ignorierten
`node_modules/` ausnehmen. Die konkrete `.gitignore`-Regel
und Git-Prüfung zeigt der
[Shiller Theme Setup Skill](skills/shiller-theme-setup/SKILL.md).

Die Library enthält den Seitenzugriff über `ShillerDir` und getrennt davon die
Installation von Theme-Vorlagen über `Leuffen\Shiller\Automation\ShillerAutomation`.
Projekt-, Document-Root- und Template-Konfiguration werden über
`Leuffen\Shiller\Automation\ShillerAutomationFactory` aufgelöst.

## CLI: Kundenänderungen erfassen und Lösungen planen

`shiller edit create` sammelt Kundenwünsche als `T_Changes`-YAML, ohne
Website-Dateien zu ändern. Beim ersten parameterlosen Aufruf legt es
`.shiller-edit.d/` in der Projektwurzel an und fordert dazu auf,
`.md`-, `.txt`-, `.pdf`-, `.jpg`-, `.jpeg`- oder `.png`-Dateien
hineinzukopieren. Erst nach Bestätigung mit `Y` und Enter beginnt die
Analyse. Bei vorhandenem Verzeichnis werden die Quellen direkt gelesen.
Explizite Quelldateien und Verzeichnisse bleiben möglich.

```sh
shiller edit create
shiller edit create .shiller-edit.d/
shiller edit create .shiller-edit.d/feedback.md .shiller-edit.d/scan.pdf
shiller edit create --context briefing.md --skills-dir skills
```

Die generierte Datei liegt standardmäßig unter
`.shiller-edit.d/shiller-edit.yml`. Alle `.yml`- und `.yaml`-Dateien
werden beim Einlesen des Verzeichnisses ausgeschlossen und **nicht** an
die AI übertragen. Ein vorhandenes Ergebnis wird bei `create` nicht
überschrieben; ein alternatives Ziel kann mit `--output` gewählt werden.

Die Struktur enthält `quotes` (Originalstellen einschließlich Text
ohne Aufgabenbezug), `tasks` (identifizierte Änderungswünsche) und
`resolutions` (bei `create` immer leer). Jeder Task enthält:

- `id` – stabile ID, z. B. `T-0001`
- `summary` – Beschreibung des Änderungswunsches
- `questions` – offene Fragen, die `resolve` bei fehlenden Angaben hinterlegt
- `adminInformation` – manuell ergänzbare Hinweise für die Planung

Bei Markdown und TXT werden die Inhalte als `FilePrompt` übergeben, PDFs
als Dokumente und JPEG/PNG als `ImagePrompt`. Die Quotes sollen den
Originaltext möglichst vollständig wiedergeben. PDFs und Bilder
ermöglichen keine bytegenaue Textprüfung.

### Lösungsplanung mit `shiller edit resolve`

```sh
shiller edit resolve
shiller edit resolve --input .shiller-edit.d/shiller-edit.yml
shiller edit resolve --project-dir /srv/website --document-root docs
shiller edit resolve --skills-dir skills --context briefing.md
```

`resolve` verwendet die bestehende YAML, das Projektverzeichnis,
den Projektkontext aus `.shiller-context.d/` und passende Theme-Skills
aus `skills_dir`. Das Modell kann relevante Edit-Skills und References
über `skill_load` laden. Als Planungsgrundlage stehen die Dateiliste des
Document Roots und kleine, lesbare Website-Textdateien zur Verfügung.
Die gemeinsame `ShillerAiContextFactory` übernimmt die Einstellungen
aus `.shiller.yml` für Modell, Client, Reasoning, Web-Tool und Timeouts.

Offene Tasks werden gemeinsam analysiert. Das AI-Ergebnis verwendet direkt
`T_Changes`, `T_Task` und `T_Resolution`, ohne Assessment-Zwischentyp.
Ein Task hat den Typ `content` oder `style`; eine Resolution kann mehrere
zusammengehoerende Tasks desselben Typs umfassen. Sie enthaelt `id`,
`taskIds`, `description`, `defect` und eine Liste `files` mit einer oder
mehreren Projektdateien.
Kann trotz Projektdateien, Skills und `adminInformation` keine
verlässliche Lösung ermittelt werden, entsteht **keine** Resolution;
stattdessen werden die benötigten Angaben in `questions` vermerkt.
Bereits vorhandene Resolutions werden nicht neu erzeugt. Nach Ergänzen
der `adminInformation` kann `resolve` für offene Tasks erneut laufen.

`resolve` aktualisiert ausschließlich die Edit-YAML. Es führt keine
Änderung an Website-Dateien aus und ist keine Solve-/Apply-Phase.

### Interaktiv verfeinern: `shiller edit refine`

```sh
shiller edit refine
shiller edit refine --project-dir /srv/website --document-root docs
```

Die Terminal-Eingabe nutzt `phore/cli2` und `In::AskMultiLine()`:
Enter sendet eine Anweisung, Ctrl+J oder Shift+Enter fuegt einen Zeilenumbruch
ein, und mehrzeiliges Einfuegen wird unterstuetzt. Freitext wie
"Fuehre T-0001 und T-0002 zu einer Aufgabe zusammen und aktualisiere die
Resolution" verfeinert `T_Changes` anhand der vorhandenen Dateien und
Theme-Skills. Die Original-Zitate bleiben erhalten; neue Zuordnungen in
`quotes[].quotes[].taskIds` und `resolutions[].taskIds` werden validiert.

Mit `:show` erscheinen die aktuellen Tasks, `:undo` nimmt die letzte AI-Runde
zurueck, `:save` schreibt die Edit-YAML und `:quit` verlaesst den Dialog
ohne Schreiben. Website-Dateien bleiben unveraendert. Nach bereits
protokollierten Ausfuehrungen ist Refine gesperrt, damit die Historie
ihre stabilen Task-IDs behaelt.

### Anwenden und Diffs aufzeichnen: `shiller edit execute`

```sh
shiller edit execute --dry-run
shiller edit execute --task-id T-0001
shiller edit execute --yes
```

Die Action setzt Tasks mit bestehender Resolution nacheinander um. Eine
gemeinsame Resolution mit mehreren Task-IDs wird trotzdem je Task separat
angewendet und nachgewiesen. Ohne `--yes` muss jeder Schreibvorgang bestaetigt
werden. `--dry-run` zeigt nur Diffs; es veraendert weder Dateien noch
das Protokoll. Tasks mit offenen Fragen werden nicht ausgefuehrt.

Geschrieben werden nur vorhandene, in `resolution.files` angegebene UTF-8-
Projektdateien (bis 80 KB). Der gesamte Zielinhalt muss von der AI mit
dem korrekten SHA-256-Hash des Originals kommen. Ungeplante Pfade und
ungueltige/stale Antworten werden vor einem Schreibzugriff abgewiesen.
Fuer jede Aufgabe entsteht in `.shiller-edit.d/shiller-edit-changes.yml` ein
einheitliches, nach Task und Dateipfad filterbares Protokoll:

```yaml
version: 1
changes:
  - taskId: T-0001
    resolutionId: R-0001
    file: docs/index.md
    beforeSha256: "..."
    afterSha256: "..."
    hunks:
      - oldStart: 3
        oldLines: 1
        newStart: 3
        newLines: 1
        diff: |
          @@ -3,1 +3,1 @@
          -Alte Adresse
          +Neue Adresse
```

Jede weitere geaenderte Datei erhaelt einen eigenen Eintrag mit derselben
Task-ID. Diffs referenzieren die tatsaechlichen Zeilennummern vor und
nach genau dieser Task-Ausfuehrung. Bereits protokollierte Tasks werden bei
erneuter Ausfuehrung uebersprungen. Neue Dateien und Binaerdateien sind
absichtlich nicht Teil dieses Schreib-Workflows.

## Website aus `_tpl` anlegen

Das Theme-Paket liefert `_tpl/_root/` für die bedingungslose Grundstruktur und
weitere Vorlagen unter `_tpl/`. `init()` kopiert `_root` in das Projekt und
installiert danach Vorlagen mit mindestens einem ausgewählten Tag im gewählten Document Root. `install()`
installiert nur markierte Vorlagen. Bereits vorhandene Zieldateien werden
überschrieben; zwei gleichzeitig ausgewählte Vorlagen für dasselbe Ziel sind
ein Fehler. Vor dem Kopieren werden sämtliche Ziele geprüft.

Für normale Nutzung erhält die Factory ein Startverzeichnis und übernimmt die
gesamte Auflösung von Projektwurzel, Document Root, `.shiller.yml` und
`template_dir`:

```php
<?php

use Leuffen\Shiller\Automation\ShillerAutomationFactory;

$automation = (new ShillerAutomationFactory('/srv/site'))->create(
    documentRoot: 'docs',
    templateDir: './node_modules/@leuffen/themejs2/_tpl',
);
$written = $automation->init(['raven']);
// $written enthält Pfade relativ zur Projektwurzel, z. B. docs/index.md.
```

Wird `templateDir` weggelassen, liest die Factory `template_dir` aus
`<projekt>/.shiller.yml`. Damit kann dieselbe Auflösung unabhängig vom
CLI auch aus Anwendungen, Jobs oder Tests verwendet werden.

Markdown-Dateien unter `_tpl` werden ausgewählt, wenn ihr YAML Front Matter
einen `shiller`-Block enthält. Dieser Block bleibt in der installierten Datei
für spätere Bearbeitung erhalten:

```yaml
---
shiller:
  tags: [base, seem2]
  target: index.md
layout: website
---
```

Andere Textdateien können als `.template` geliefert werden. Ihr erstes YAML
Front Matter enthält denselben `shiller`-Block; dieser Header wird bei der
Installation entfernt, ebenso die Dateiendung `.template`. Eine Vorlage
`navbar.osman.html.template` kann so `docs/_includes/navbar.html` erzeugen, wenn `docs` der Document Root ist:

```yaml
---
shiller:
  tags: [theme:osman]
  target: _includes/navbar.html
---
<nav>...</nav>
```

In kopierten Markdown-Dateien wird der Shiller-Block auf
`source: pages/index.base.md` reduziert. Der Pfad ist relativ
zum konfigurierten `_tpl/`-Verzeichnis; so bleibt die Originalvorlage
auffindbar und `revert` kann sie direkt wiederherstellen.


## Installierte Inhalte an neuen Kontext anpassen

Nach init/install bearbeitet `shiller ai adjust` Markdown und YAML unter
`_data/` mit dem neuen `AiContext` aus phore/ai-harness. Der Selector
unterstuetzt Dateien, Globs und `tag:<name>`.

```sh
shiller ai adjust index.md
shiller ai adjust index.md _data/general.yml
shiller ai adjust "leistungen/**/*.md" "tag:arzt"
shiller ai --debug --context "context/zusatz.md" adjust index.md
shiller revert index.md "_data/*.yml"
```

Internetrecherche ist bei `ai adjust` standardmaessig deaktiviert.
In `.shiller.yml` kann sie gezielt aktiviert werden:

```yaml
ai:
  reasoning_effort: medium
  web_access: true
```

Die Factory liest `skills_dir` aus der `.shiller.yml`; ohne Angabe gilt
`node_modules/@leuffen/themejs2/skills` relativ zur Projektwurzel.
Konfigurierte relative Skill-Pfade beziehen sich auf die `.shiller.yml`.
Die SkillCollection erkennt die darin publizierten ThemeJS2-Skills und
laedt deren Instructions und References nach Bedarf. Ein optionaler
Basis-Skill aus `resources/skills/adapt-content/SKILL.md` ergaenzt sie.

Projektkontext wird zuerst aus `.shiller-context.d/project.md`, danach
den weiteren Markdown-Dateien direkt in `.shiller-context.d/` geladen;
`*.rules.md` und `raw/` bleiben davon ausgenommen. `--context` kann
weitere Quellen ergaenzen. AiContext analysiert den gemeinsamen Kontext
zuerst und setzt danach einen Checkpoint. Von diesem Ausgangspunkt wird
jede ausgewaehlte Datei separat angepasst; erst nach erfolgreichen
AI-Aufrufen werden alle Ergebnisinhalte geschrieben.

Sidecars `<zieldatei>.d.*` werden ebenfalls beruecksichtigt.
`--debug` ergaenzt Skill- und Datei-Logging. Der bisherige Einstieg
`shiller adapt --select "index.md"` bleibt verfuegbar; Rule-Prioritaeten,
`--event` und `--mode` werden nicht mehr verwendet.

## Projektkontext aus Rohdaten aufbauen

`shiller context build` aktualisiert
`.shiller-context.d/project.md` mit `phore/ai-harness`. Ohne Quelle wird
`.shiller-context.d/raw/` rekursiv verarbeitet. Bereits erfolgreich
verarbeitete und unveränderte Dateien werden anhand ihres Content-Hashes in
`.shiller-context.d/.raw-state.json` übersprungen.

Eine explizit angegebene Datei oder ein Verzeichnis wird bewusst erneut
analysiert. Damit kann derselbe Input mit einem neuen `--focus` noch einmal
ausgewertet werden:

```sh
shiller context build
shiller context build kundendaten.pdf
shiller context build imports/kunde --focus "Nur Leistungen und Kontaktdaten"
```

Der mitgelieferte Build-Skill liegt unter
`resources/skills/build-context/SKILL.md`. Optionale projektspezifische\nZusatzregeln stehen in `.shiller-context.d/project.rules.md`; sie gelten nur\nfür `context build` und werden getrennt vom normalen Kontext geladen. Die\nvorhandene `project.md` ist
zugleich Vorlage und bestehender, manuell pflegbarer Kontext. Informationen,
die in neuen Quellen nicht vorkommen, bleiben erhalten. Eindeutige
Aktualisierungen dürfen bestehende Fakten ändern; unklare Widersprüche werden
als offene Punkte festgehalten statt stillschweigend überschrieben zu werden.

Sidecars wie `_data/general.yml.d.json` liefern Feldbedeutung und
Strukturhinweise. Markdown-Front-Matter und bestehende Layouts bleiben
grundsaetzlich erhalten; fuer `_data`-YAML gelten die vorhandenen
Keys und Strukturen.

## Document Root und mehrere Websites

`shiller.target` ist **immer relativ zum Document Root**: `index.md` schreibt
`docs/index.md`, `_includes/navbar.html` schreibt
`docs/_includes/navbar.html`. Dateien aus `_tpl/_root/docs/` werden ebenfalls
dorthin kopiert. Andere Dateien aus `_tpl/_root/` bleiben relativ zur
Projektwurzel, zum Beispiel `package.json`.

Die Installation nutzt `.shiller.yml` **in der Projektwurzel**. Pfade in
`template_dir` werden relativ dazu aufgelöst; beim mitgelieferten ThemeJS2
ist das `node_modules/@leuffen/themejs2/_tpl`.
Ein expliziter relativer `templateDir`-Wert der Factory wird dagegen relativ
zur Projektwurzel aufgelöst. So kann jede Website in einem eigenen
Projektverzeichnis neben anderen Websites liegen.

## Weitere Aufrufe

Die ausführliche CLI-Reihenfolge steht am Anfang dieser README; der
verbindliche Template-Vertrag in [AGENTS.md](AGENTS.md) und die
[AI Usage Info](.ai-usage-info.md) liefern Layout und Beispiele.

Der bisherige [Seiten-API-Entwurf](docs/proposals/2026-09-12-shiller-seiten-api.md)
und die [Seitenbeispiele](examples/README.md) beschreiben den separaten
`ShillerDir`-Bereich.

## Vollstaendiges Demo-Projekt

Unter [demo/](demo/) stehen Template und installierte Website getrennt.
Die Beispiele zeigen den zentralen Projektkontext, konfigurierbares
`skills_dir`, Markdown-Zieldateien und YAML-Sidecars. Bearbeitungsregeln
werden ausschliesslich durch die ThemeJS2-Skills bereitgestellt.
