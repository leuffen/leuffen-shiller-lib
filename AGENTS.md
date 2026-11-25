# Repository-Regeln

## Interne Basis-Skills

Unter `resources/skills/` liegen die von Shiller intern verwendeten
Basis-Skills. Sie sind keine allgemeinen Theme- oder Projekt-Skills und
werden ausschließlich durch den jeweils dafür vorgesehenen Workflow
geladen. Der Skill `resources/skills/adapt-content/SKILL.md` darf nur
für die Content-Anpassung über `shiller ai adjust` beziehungsweise die
zugehörige `ShillerContentAction` eingesetzt werden. Andere Befehle,
insbesondere Init, Install, Context Build und Revert, dürfen ihn nicht
laden oder als allgemeine Anweisung übernehmen. Weitere Theme- und
Seitenspezifische Skills stammen aus dem konfigurierten `skills_dir`.

## Dateisystemzugriff

Für Datei- und Verzeichniszugriffe sowie sicherheitsrelevante Pfad-, Root-, Symlink-, Hardlink-, Existenz-, Lesbarkeits-, Schreibbarkeits- und Traversierungsprüfungen ist ausschließlich `phore/filesystem` zu verwenden. Restrictions werden am Phore-Einstieg gebunden und über die daraus abgeleiteten `PhoreUri`-, `PhoreFile`- und `PhoreDirectory`-Objekte weitergereicht; native PHP-Dateisystemprüfungen, eigene Walk- oder Pfadvalidierungs-Helper und parallele Catch-and-Re-throw-Logik dürfen dafür nicht ergänzt werden. Shiller-eigene fachliche Validierungen, etwa Tags, Referenzsyntax oder Kollisionen innerhalb eines Installationsplans, bleiben Aufgabe dieser Library.

## Containerübergreifende Dateirechte

Alle von Shiller angelegten oder geschriebenen Dateien müssen `0666`
(world-readable und world-writable), alle neu angelegten Verzeichnisse `0777`
(zusätzlich für alle Benutzer durchsuchbar beziehungsweise ausführbar) erhalten.
So bleiben die Inhalte zwischen Containern mit unterschiedlichen Benutzer- und
Gruppen-IDs nutzbar. Reguläre Dateien erhalten dadurch keine Ausführungsrechte.

Eine restriktive `umask` darf diese Zielrechte nicht reduzieren. Wird die
`umask` vorübergehend geändert, muss ihr ursprünglicher Wert auch im Fehlerfall
wiederhergestellt werden. Rechteänderungen und Verzeichnisanlagen erfolgen über
`phore/filesystem`. Unveränderte Bestandsdateien und bestehende Verzeichnisse
werden nicht nachträglich pauschal umberechtigt. Neue Schreibpfade müssen die
Zielrechte auch unter restriktiver `umask` testen.

## Template-Paket: Verzeichnisvertrag

Ein Shiller-kompatibles Website-Template ist ein Paket mit den beiden
Verzeichnissen `_tpl/` (Dateivorlagen) und `skills/` (AI-Anweisungen).
Das Paket wird beispielsweise als `node_modules/@leuffen/themejs2/` im
Website-Projekt installiert. `_tpl/_root/` ist die bedingungslos zu
installierende Startstruktur; `_tpl/pages/` enthaelt beispielhafte
tag-gesteuerte Varianten. Die Skills bleiben im Paket und werden nicht
als Website-Dateien kopiert.

```text
@leuffen/themejs2/
|-- _tpl/
|   |-- _root/
|   |   |-- .shiller.yml
|   |   |-- .shiller-context.d/
|   |   |   `-- project.md
|   |   |-- docs/
|   |   |   |-- _data/
|   |   |   |   `-- general.yml
|   |   |   `-- _includes/
|   |   `-- package.json
|   |-- pages/
|   |   |-- index.base.md
|   |   |-- index-anleitung.md
|   |   `-- navbar.osman.html.template
`-- skills/
    `-- edit-themejs2-site/
        |-- SKILL.md
        `-- references/
            `-- startseite.md
```

### Kopier- und Zielregeln

`shiller init` uebernimmt alle Dateien aus `_tpl/_root/` unabhaengig
von Tags. `_tpl/_root/docs/**` wird in den gewaehlten Document Root
(Standard: `docs/`) kopiert; alle anderen Dateien aus
`_tpl/_root/` landen relativ zur Projektwurzel. So wird etwa
`_tpl/_root/docs/_data/general.yml` zu
`<projekt>/docs/_data/general.yml`, waehrend
`_tpl/_root/package.json` zu `<projekt>/package.json` wird.
Mit `--root public` wird `_tpl/_root/docs/index.md` nach
`<projekt>/public/index.md` kopiert.

Weitere Markdown-Dateien und `*.template`-Dateien unter `_tpl/`
werden nur installiert, wenn mindestens ein `shiller.tags`-Wert mit
`--tags` uebereinstimmt. `shiller.target` ist stets relativ
zum Document Root, beispielsweise `index.md` oder
`_includes/navbar.html`. Eine bereits vorhandene Zieldatei wird
ueberschrieben; mehrere ausgewaehlte Varianten fuer dasselbe Ziel
sind ein Fehler. `shiller install` schreibt nur die ausgewaehlten
Varianten, nicht erneut die gesamte Startstruktur.

`skills/` bleibt Quelle im Template-Paket und wird nicht kopiert.
Dateispezifische Bearbeitungsregeln werden ausschliesslich durch Skills
und deren References geladen. Beim Kopieren von Markdown speichert
Shiller unter `shiller.source` den Dateipfad relativ zu `_tpl/`.

### Konfiguration und Startkontext

Fuer die CLI nach der ersten Installation gehoert eine
`_tpl/_root/.shiller.yml` in die Startstruktur. Sie wird als
`<projekt>/.shiller.yml` installiert. Bei Verwendung des ueblichen
npm-Paketpfads enthaelt sie beispielsweise:

```yaml
version: 1.0
adapter: jekyll-polyglot:1.0
template_dir: node_modules/@leuffen/themejs2/_tpl
skills_dir: node_modules/@leuffen/themejs2/skills
context_files: .shiller-context.d/project.md
```

`version` (derzeit nur `1.0`) und `adapter` (`jekyll-polyglot:1.0` oder
`jekyll-legacy:1.0`) sind Pflichtfelder; die Konfiguration beginnt mit `version`.
`template_dir` ist der Pfad zu `_tpl/`, nicht zum Paket-Root.
`skills_dir` ist separat konfigurierbar; ohne Eintrag verwendet die
Content-Action standardmaessig
`node_modules/@leuffen/themejs2/skills` relativ zur Projektwurzel.
Die `.shiller.yml` liegt stets in der Wurzel des Website-Repositories,
nicht im Document Root. Alle relativen Konfigurationspfade beziehen sich
auf diese Projektwurzel. Vorlagen, Skills und Kontextdateien befinden sich
normalerweise innerhalb des Projekts; reguläre Konfigurationen verwenden
keine `../`-Pfade aus dem Repository heraus. Beim allerersten `init` ist der Template-Pfad
explizit mit `--template-dir` anzugeben, falls noch keine
Projektkonfiguration existiert.

Die Datei `_tpl/_root/.shiller-context.d/project.md` wird nach
`<projekt>/.shiller-context.d/project.md` kopiert. Der allgemeine AI-Kontext
wird mit `context_files` in `.shiller.yml` als einzelner Pfad oder geordnete
Liste konfiguriert. Ohne Feld wird nur die vorhandene project.md geladen;
andere Dateien werden nicht automatisch eingebunden. `[]` deaktiviert den
Projektkontext. Der Context-Build bleibt davon unabhängig. Neue Rohdaten gehoeren spaeter
nach `<projekt>/.shiller-context.d/raw/`. Der Befehl
`shiller context build` aktualisiert daraus die `project.md`;
bei unveraenderten Quellen ist dieser Schritt nicht erneut notwendig.

### Static Context und User Rules

Projektvorlagen sollen unter `.shiller.d/` zwei getrennte Dateien bereitstellen:
`static-context.md` beschreibt ausschließlich Website-Technik, Layout- und
allgemeine Bearbeitungsregeln und wird über `context_files` nur lesend geladen.
`user-rules.md` ist die zentrale, editierbare Wissensquelle für bestätigte
individuelle Fakten und dauerhafte Regeln; sie wird über `user_rules_file`
konfiguriert. Neue oder geänderte Fakten werden nur nach ausdrücklicher
Zustimmung oder direktem Speicherauftrag gespeichert. Keine individuellen
Daten in den statischen Kontext übernehmen.

### Vorlagendateien und Skills

Tag-gesteuerte Markdown-Vorlagen besitzen YAML Front Matter mit
`shiller:` und einem oder mehreren `tags`; in der installierten Markdown-Datei werden `tags` und `target` durch
`source` ersetzt; andere Front-Matter-Felder bleiben erhalten:

```markdown
---
shiller:
  tags: [base, startseite]
  target: index.md
layout: website
title: Startseite
---
# Willkommen
```

Bei einer `*.template`-Vorlage wird dagegen das gesamte
Shiller-Front-Matter beim Installieren entfernt; nur der Body
wird unter `shiller.target` ohne das Suffix `.template`
gespeichert:

```html
---
shiller:
  tags: ["theme:osman"]
  target: _includes/navbar.html
---
<nav>Navigation</nav>
```

Die installierte Datei enthaelt beispielsweise `shiller.source: pages/index.base.md`.

Ein Skill liegt als `skills/<name>/SKILL.md` mit YAML-Front-Matter
(`name`, `description`) vor. Zusaetzliche
`references/` und `assets/` liegen daneben und werden vom
AI Harness je nach Aufgabe geladen. Die Content-Anpassung laedt
relevante Skills ueber die SkillCollection, analysiert zuerst den
Projektkontext und bearbeitet danach jede Zieldatei von einem
gemeinsamen Checkpoint aus. Keine `_rules.d`-Dateien vorsehen.

### Reihenfolge der Shiller-Commands

Alle Befehle werden in der Website-Projektwurzel ausgefuehrt,
nachdem das npm-Paket und die Shiller-CLI verfuegbar sind:

```sh
# 1. Grundstruktur kopieren und die erste Tag-Variante installieren.
shiller init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags base

# 2. Optional eine weitere Variante installieren/aktualisieren.
shiller install --tags "theme:osman"

# 3. Bei neuen Rohdaten erst den Projektkontext aktualisieren.
shiller context build

# 4. Inhalte gezielt mit Projektkontext und Paket-Skills anpassen.
shiller ai adjust index.md _data/general.yml
```

`shiller revert index.md` stellt bei Bedarf die Originalvorlage
wieder her und verwirft die Anpassungen dieser Datei. Die Schritte
`install` und `context build` sind optional; die
Content-Anpassung folgt erst nach der erforderlichen Installation
und Kontextaufbereitung. `ai adjust` akzeptiert Dateipfade,
Globs und `tag:<name>`-Selektoren.

### Schreibweise

Alle Produktbezeichner, CLI-Kommandos, Front-Matter-Schluessel,
Konfigurationsdateien und internen Hilfsdateien verwenden ausschliesslich
`shiller` beziehungsweise `Shiller` in der passenden
Gross-/Kleinschreibung. Es werden keine alternativen Namensformen,
Aliases oder Kompatibilitaets-Fallbacks gepflegt.
