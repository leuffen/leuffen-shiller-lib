# Repository-Regeln

## Dateisystemzugriff

Für Datei- und Verzeichniszugriffe sowie sicherheitsrelevante Pfad-, Root-, Symlink-, Hardlink-, Existenz-, Lesbarkeits-, Schreibbarkeits- und Traversierungsprüfungen ist ausschließlich `phore/filesystem` zu verwenden. Restrictions werden am Phore-Einstieg gebunden und über die daraus abgeleiteten `PhoreUri`-, `PhoreFile`- und `PhoreDirectory`-Objekte weitergereicht; native PHP-Dateisystemprüfungen, eigene Walk- oder Pfadvalidierungs-Helper und parallele Catch-and-Re-throw-Logik dürfen dafür nicht ergänzt werden. Shiller-eigene fachliche Validierungen, etwa Tags, Referenzsyntax oder Kollisionen innerhalb eines Installationsplans, bleiben Aufgabe dieser Library.

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
template_dir: node_modules/@leuffen/themejs2/_tpl
skills_dir: node_modules/@leuffen/themejs2/skills
```

`template_dir` ist der Pfad zu `_tpl/`, nicht zum Paket-Root.
`skills_dir` ist separat konfigurierbar; ohne Eintrag verwendet die
Content-Action standardmaessig
`node_modules/@leuffen/themejs2/skills` relativ zur Projektwurzel.
Relative Pfade aus der Projektkonfiguration beziehen sich auf den Ort
der `.shiller.yml`. Beim allerersten `init` ist der Template-Pfad
explizit mit `--template-dir` anzugeben, falls noch keine
Projektkonfiguration existiert.

Die Datei `_tpl/_root/.shiller-context.d/project.md` wird nach
`<projekt>/.shiller-context.d/project.md` kopiert und ist der
Startkontext fuer die Content-Anpassung. Neue Rohdaten gehoeren spaeter
nach `<projekt>/.shiller-context.d/raw/`. Der Befehl
`shiller context build` aktualisiert daraus die `project.md`;
bei unveraenderten Quellen ist dieser Schritt nicht erneut notwendig.

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
