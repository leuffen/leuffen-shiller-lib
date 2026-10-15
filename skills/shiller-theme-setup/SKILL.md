---
name: shiller-theme-setup
description: Bereitet ein ThemeJS2-Theme für Shiller vor und prüft, ob eine neue oder bestehende Website damit initialisiert und per AI angepasst werden kann. Verwenden, wenn _tpl-Vorlagen, shiller-Tags, Theme-Skills, .shiller.yml oder das Einchecken von node_modules/@leuffen/themejs2 in einem Website-Repository eingerichtet oder angepasst werden sollen.
---

# Shiller Theme Setup

Richte das **Theme-Paket** so ein, dass die Shiller-CLI daraus eine Website
initialisieren und vorhandene Inhalte mithilfe der Theme-Skills bearbeiten kann.
Verwende für den vollständigen Dateivertrag die
[AGENTS.md](../../AGENTS.md); für die CLI-Schritte die
[README.md](../../README.md).

## 1. Paketstruktur herstellen

Prüfe zuerst das tatsächlich vorhandene Theme-Verzeichnis. Das gewünschte
Layout des npm-Pakets `@leuffen/themejs2` ist:

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
|   |   |   `-- index.md
|   |   `-- package.json
|   |-- pages/
|   |   `-- leistungen.base.md
`-- skills/
    `-- edit-website/
        |-- SKILL.md
        `-- references/
            `-- textstil.md
```

`_tpl/_root/` enthält die **unbedingte Startstruktur**.
`shiller init` kopiert `_tpl/_root/docs/**` in den
Document Root (standardmäßig `docs/`) des neuen Website-Projekts;
alle übrigen `_root/`-Dateien in dessen Projektwurzel.
Andere Dateien unter `_tpl/` werden nur über ihre Shiller-Tags
installiert. `skills/` bleibt im Theme-Paket und wird nicht als Website-Datei kopiert.

## 2. Konfiguration und Kontext liefern

Lege `_tpl/_root/.shiller.yml` an, damit sie im Website-Projekt
als `.shiller.yml` verfügbar wird:

```yaml
template_dir: node_modules/@leuffen/themejs2/_tpl
skills_dir: node_modules/@leuffen/themejs2/skills
```

Beide relativen Pfade beziehen sich auf die Projektwurzel, in der diese
Datei nach `init` liegt. Verwende als Startkontext
`_tpl/_root/.shiller-context.d/project.md` mit bearbeitbaren Angaben
zu Website, Leistungen, Ansprechpartnern, Tonalität und Kontaktdaten.
Neue Quellen werden im Zielprojekt unter
`.shiller-context.d/raw/` gesammelt und bei Bedarf mit
`shiller context build` eingearbeitet.

## 3. Installierbare Vorlagen kennzeichnen

Markdown-Vorlagen außerhalb von `_root/` benötigen YAML Front Matter
mit `shiller.tags` und ggf. `shiller.target`. Beispiel für
`_tpl/pages/leistungen.base.md`:

```markdown
---
shiller:
  tags: [base, leistungen]
  target: leistungen.md
layout: website
title: Leistungen
---
# Unsere Leistungen

Hier stehen zunächst neutrale Platzhalter.
```

`--tags base` installiert diese Datei als
`docs/leistungen.md`; `shiller.target` ist relativ zum
Document Root. In die installierte Markdown-Datei wird ausschliesslich `shiller.source`
als Pfad relativ zu `_tpl/` geschrieben, z. B. `pages/leistungen.base.md`.
Die Auswahlfelder `tags` und `target` bleiben nur in der Vorlage.

Für andere Textdateien eine `*.template`-Datei mit Shiller-Header
verwenden, beispielsweise `_tpl/pages/navbar.html.template`:

```html
---
shiller:
  tags: ["theme:osman"]
  target: _includes/navbar.html
---
<nav>Navigation</nav>
```

Im Ziel landet nur der Body als `docs/_includes/navbar.html`,
nicht das Shiller-Front-Matter oder das Suffix `.template`.
Vorlagen mit demselben Ziel nicht gleichzeitig auswählen.
Ausschließlich `shiller`-Schreibweise verwenden, keine Legacy-Aliase.

## 4. Bearbeitungs-Skills im Theme bereitstellen

Lege pro konkreter Website-Aufgabe einen Skill unter
`skills/<name>/SKILL.md` an. Ein minimaler
`skills/edit-website/SKILL.md`-Inhalt ist:

```markdown
---
name: edit-website
description: Bearbeitet Texte einer mit ThemeJS2 aufgebauten Shiller-Website unter Beachtung der bestehenden Komponenten und des Projektkontexts.
---

Erhalte Layouts, Komponenten, Links und technische Front-Matter-Felder.
Passe Texte nur anhand der gelieferten Projektfakten an.
Lade bei Bedarf Hinweise aus references/textstil.md.
```

Fachliche Detailhinweise können in `references/`, wiederverwendbare
Materialien in `assets/` liegen. Die AI-Anpassung lädt passende
Skills aus dem konfigurierten `skills_dir`. Der vorliegende
`shiller-theme-setup`-Skill ist für das **Einrichten des Themes**;
er ersetzt nicht dessen redaktionelle Website-Skills.
Keine alten `_rules.d/`-Strukturen neu anlegen.

## 5. Theme-Paket im Website-Repository einchecken

Wenn eine Website **ohne erneutes `npm install`** bearbeitet
werden soll, muss das konkrete Theme-Paket inklusive
`_tpl/`, `skills/` und referenzierten Dateien im
Website-Repository versioniert sein. Ändere dazu die **.gitignore
des Website-Repositories**, nicht die der Shiller-Library.

Ersetze eine pauschale Regel wie `/node_modules/` oder
`node_modules/` durch folgende Regeln. Ein bloßes zusätzliches
`!node_modules/@leuffen/themejs2/` unter einer ignorierten
`node_modules/`-Directory funktioniert **nicht**, weil Git keine
Dateien unter ignorierten Parent-Verzeichnissen wieder einschließt.

```gitignore
# Alle npm-Pakete ignorieren, außer @leuffen/themejs2.
/node_modules/*
!/node_modules/@leuffen/
/node_modules/@leuffen/*
!/node_modules/@leuffen/themejs2/
```

Prüfe zusätzliche globale und verschachtelte `.gitignore`-Regeln.
Danach im Website-Repository prüfen und das Theme bewusst hinzufügen:

```sh
git check-ignore -v node_modules/@leuffen/themejs2/skills/edit-website/SKILL.md
git add -A -- node_modules/@leuffen/themejs2/
git ls-files node_modules/@leuffen/themejs2/
```

`git check-ignore` darf keinen weiterhin wirksamen Ignore-Eintrag
für die Skill-Datei melden; bei einer negierenden Regel zeigt Git diese
gegebenenfalls mit führendem `!` an. `git ls-files`
muss tatsächliche Template- und Skill-Dateien auflisten, nicht bloß
einen Symlink auf das Paket. Falls es ein npm-Workspace-Symlink ist,
die Paketdateien als echte Dateien bereitstellen. Ein späteres
`npm install` oder `npm ci` kann versionierte Paketdateien
überschreiben; solche Änderungen vor dem Commit bewusst kontrollieren.
Andere `node_modules`-Pakete bleiben ignoriert.

## 6. Einen neuen Website-Setup-Lauf prüfen

Nachdem das PHP-Paket `leuffen/shiller-lib` und das Theme
vorliegen, aus der **Website-Projektwurzel**:

```sh
./vendor/bin/shiller init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags base
./vendor/bin/shiller install --tags "theme:osman"
# Nur bei neuen Rohdaten:
./vendor/bin/shiller context build
./vendor/bin/shiller adapt --select index.md
./vendor/bin/shiller revert index.md
```

Prüfe, ob die Startdateien nach `docs/` kopiert wurden,
`.shiller.yml` und `.shiller-context.d/project.md`
existieren und die konfigurierten Skills lesbar sind. `install`
und `revert` können bestehende Website-Inhalte überschreiben:
in bestehenden Projekten nur gezielt und nach Sichtprüfung ausführen.
Keine nicht angeforderten Theme-Dateien oder fremden Repositories ändern.

## 7. Bestehende Markdown-Vorlagen reworken

Bei einem Rework alle Legacy-Front-Matter-Felder entfernen, deren Name
mit `_shiller` beginnt, sowie weitere obsolete Shiller-Attribute.
Im kopierten Markdown bleibt innerhalb des YAML Front Matter nur
`shiller.source` (relativ zum `_tpl/`-Verzeichnis), keine Tags,
Targets oder Instructions. In der installierbaren Quelle sind
`shiller.tags` und optional `shiller.target` fuer die Auswahl erlaubt.
Entferne aus den Vorlagen ausserdem `pid` und `lang`, sofern vorhanden,
und benenne `*.de.md` in `*.md` um. Sonstige Front-Matter-Felder
und Inhalte unveraendert lassen.
