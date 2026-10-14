# Shiller Library

Die Library enthält den Seitenzugriff über `ShillerDir` und getrennt davon die
Installation von Theme-Vorlagen über `Leuffen\Shiller\Automation\ShillerAutomation`.
Projekt-, Document-Root- und Template-Konfiguration werden über
`Leuffen\Shiller\Automation\ShillerAutomationFactory` aufgelöst.

## Website aus `_tpl` anlegen

Das Theme-Paket liefert `_tpl/_root/` für die bedingungslose Grundstruktur und
weitere Vorlagen unter `_tpl/`. `init()` kopiert `_root` in das Projekt und
installiert danach Vorlagen mit mindestens einem ausgewählten Tag im gewählten Document Root. `install()`
installiert nur markierte Vorlagen. Bereits vorhandene Zieldateien werden
überschrieben; zwei gleichzeitig ausgewählte Vorlagen für dasselbe Ziel sind
ein Fehler. Vor dem Kopieren werden sämtliche Ziele und Referenzen geprüft.

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
`<document-root>/.shiller.yml`. Damit kann dieselbe Auflösung unabhängig vom
CLI auch aus Anwendungen, Jobs oder Tests verwendet werden.

Markdown-Dateien unter `_tpl` werden ausgewählt, wenn ihr YAML Front Matter
einen `shiller`-Block enthält. Dieser Block bleibt in der installierten Datei
für spätere Bearbeitung erhalten:

```yaml
---
shiller:
  tags: [base, seem2]
  target: index.md
  instructions:
    - ./index-anleitung.md
    - tpl:/instructions/schreibstil.md
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
  instructions: tpl:/instructions/navbar.md
---
<nav>...</nav>
```

`./` verweist auf eine Anleitungsdatei neben der Quelle, `tpl:/` auf eine
Datei relativ zu `_tpl`. Anleitungen werden geprüft, aber nicht installiert.
Bei Markdown werden relative Anleitungen für spätere Verwendung nach `tpl:/`
umgeschrieben. Das Projekt muss deshalb den Template-Pfad weiterhin kennen;
die `.shiller.yml` im Document Root verwendet dafür `template_dir`. Die KI-Bearbeitung
dieser Anleitungen ist noch kein Teil der Automation.


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

Vorhandene `shiller.instructions`-Referenzen der jeweiligen Markdown-Datei
und Sidecars `<zieldatei>.d.*` werden ebenfalls beruecksichtigt.
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

Bearbeitungsregeln liegen im jeweiligen Document Root unter `_rules.d/*.md`.
Jede Rule besitzt Front Matter mit `selector` als String oder Liste, optional
`on` als Event-Filter und optional `important: true`. Fehlt `on`, gilt die
Rule fuer jedes Event; ist `on` gesetzt, wird sie bei anderen Events
vollstaendig ignoriert. Fuer jede passende Rule wird die Spezifitaet als
`1 / Anzahl der vom Selector aktuell getroffenen editierbaren Dateien`
berechnet. Bei mehreren passenden Selectoren einer Rule zaehlt der spezifischste.

Alle passenden normalen Rules werden von niedriger zu hoher Spezifitaet in den
Prompt aufgenommen; danach folgen `important`-Rules ebenfalls von niedriger zu
hoher Spezifitaet. Damit steht die hoechste Prioritaet zuletzt. Bei gleicher
Prioritaet entscheidet der Rule-Dateiname deterministisch. `--event` waehlt
den Event-Typ, standardmaessig `edit`; `--debug` gibt vor den AI-Requests
Rule-Datei, Selector, Match-Anzahl, Spezifitaet, `important` und Reihenfolge
aus. Fuer jede Zieldatei werden ausserdem vorhandene Sidecars mit dem Namensschema
`<zieldatei>.d.*` als Beschreibungsdaten angehaengt, zum Beispiel
`_data/general.yml.d.json`.
Bei Markdown darf der Skill neben dem Body auch `title`, `order` und
`description` anpassen. Bei `_data`-YAML bleiben Keys und Struktur grundsätzlich
erhalten und werden anhand von Kontext und Sidecar-Beschreibung mit neuen Werten
gefüllt.

## Document Root und mehrere Websites

`shiller.target` ist **immer relativ zum Document Root**: `index.md` schreibt
`docs/index.md`, `_includes/navbar.html` schreibt
`docs/_includes/navbar.html`. Dateien aus `_tpl/_root/docs/` werden ebenfalls
dorthin kopiert. Andere Dateien aus `_tpl/_root/` bleiben relativ zur
Projektwurzel, zum Beispiel `package.json`.

Die Konfiguration liegt als `.shiller.yml` **im Document Root**. Pfade in
`template_dir` werden relativ zu diesem Verzeichnis aufgelöst; beim
mitgelieferten ThemeJS2 ist das `../node_modules/@leuffen/themejs2/_tpl`.
Ein expliziter relativer `templateDir`-Wert der Factory wird dagegen relativ
zur Projektwurzel aufgelöst. So kann jede Website in einem eigenen
Projektverzeichnis neben anderen Websites liegen.

## Kommando

Composer stellt `bin/shiller` bereit. Das CLI ist nur ein Parameteradapter
für `ShillerAutomationFactory` und verwendet das aktuelle Arbeitsverzeichnis
als Startverzeichnis. Ohne `--template-dir` wird `template_dir` aus
`.shiller.yml` im Document Root gelesen. Ohne `--root` wird `docs` im
aktuellen Projekt verwendet; `--root` bezeichnet direkt ein anderes
Document-Root-Verzeichnis. Der erste Aufruf kann dieses Verzeichnis über
`_root/docs/` anlegen.

```sh
shiller init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags raven
shiller install --tags raven
shiller init --root /srv/site-a/public --template-dir /srv/site-a/node_modules/@leuffen/themejs2/_tpl --tags raven
```

Beim ersten Lauf wird der Paketpfad explizit übergeben. Danach enthält die kopierte
`docs/.shiller.yml` den `template_dir` für weitere Aufrufe. Ein expliziter
relativer `--template-dir`-Pfad bezieht sich auf die Projektwurzel.

Der bisherige [Seiten-API-Entwurf](docs/proposals/2026-09-12-shiller-seiten-api.md)
und die [Seitenbeispiele](examples/README.md) beschreiben den separaten
`ShillerDir`-Bereich.

## Vollstaendiges Demo-Projekt

Unter [`demo/`](demo/) sind Template und nutzendes Webseitenprojekt getrennt:
[`demo/template/`](demo/template/) repraesentiert das Template-Projekt mit
`_tpl/_root` und Vorlagen, [`demo/project/`](demo/project/) den installierten
Webseitenstand, in dem Shiller ausgefuehrt wird. Das Projekt zeigt
`docs/_rules.d`, `.shiller-context.d`, mehrere Content-Dateien und ein
`_data`-Sidecar. Die Rule-Beispiele demonstrieren mehrere Selector, dynamische
Spezifitaet, `on: user-request` und `important: true`.
