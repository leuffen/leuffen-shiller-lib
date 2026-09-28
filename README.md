# Schiller Library

Die Library enthält den Seitenzugriff über `SchillerDir` und getrennt davon die
Installation von Theme-Vorlagen über `Leuffen\Schiller\Automation\SchillerAutomation`.

## Website aus `_tpl` anlegen

Das Theme-Paket liefert `_tpl/_root/` für die bedingungslose Grundstruktur und
weitere Vorlagen unter `_tpl/`. `init()` kopiert `_root` in das Projekt und
installiert danach Vorlagen mit mindestens einem ausgewählten Tag im gewählten Document Root. `install()`
installiert nur markierte Vorlagen. Bereits vorhandene Zieldateien werden
überschrieben; zwei gleichzeitig ausgewählte Vorlagen für dasselbe Ziel sind
ein Fehler. Vor dem Kopieren werden sämtliche Ziele und Referenzen geprüft.

```php
<?php

use Leuffen\Schiller\Automation\SchillerAutomation;

$automation = new SchillerAutomation(
    projectRoot: '/srv/site',
    templateDir: '/srv/site/node_modules/@leuffen/themejs2/_tpl',
    documentRoot: 'docs',
);
$written = $automation->init(['raven']);
// $written enthält Pfade relativ zur Projektwurzel, z. B. docs/index.md.
```

Markdown-Dateien unter `_tpl` werden ausgewählt, wenn ihr YAML Front Matter
einen `schiller`-Block enthält. Dieser Block bleibt in der installierten Datei
für spätere Bearbeitung erhalten:

```yaml
---
schiller:
  tags: [base, seem2]
  target: index.md
  instructions:
    - ./index-anleitung.md
    - tpl:/instructions/schreibstil.md
layout: website
---
```

Andere Textdateien können als `.template` geliefert werden. Ihr erstes YAML
Front Matter enthält denselben `schiller`-Block; dieser Header wird bei der
Installation entfernt, ebenso die Dateiendung `.template`. Eine Vorlage
`navbar.osman.html.template` kann so `docs/_includes/navbar.html` erzeugen, wenn `docs` der Document Root ist:

```yaml
---
schiller:
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

## Document Root und mehrere Websites

`schiller.target` ist **immer relativ zum Document Root**: `index.md` schreibt
`docs/index.md`, `_includes/navbar.html` schreibt
`docs/_includes/navbar.html`. Dateien aus `_tpl/_root/docs/` werden ebenfalls
dorthin kopiert. Andere Dateien aus `_tpl/_root/` bleiben relativ zur
Projektwurzel, zum Beispiel `package.json`.

Die Konfiguration liegt als `.shiller.yml` **im Document Root**. Pfade in
`template_dir` werden relativ zu diesem Verzeichnis aufgelöst; beim
mitgelieferten ThemeJS2 ist das `../node_modules/@leuffen/themejs2/_tpl`.
So kann jede Website in einem eigenen Projektverzeichnis neben anderen
Websites liegen.

## Kommando

Composer stellt `bin/schiller` bereit. Ohne `--template-dir` wird
`template_dir` aus `.shiller.yml` im Document Root gelesen. Ohne `--root`
wird `docs` im aktuellen Projekt verwendet; `--root` bezeichnet direkt ein
anderes Document-Root-Verzeichnis. Der erste Aufruf kann dieses Verzeichnis
über `_root/docs/` anlegen.

```sh
schiller init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags raven
schiller install --tags raven
schiller init --root /srv/site-a/public --template-dir /srv/site-a/node_modules/@leuffen/themejs2/_tpl --tags raven
```

Beim ersten Lauf wird der Paketpfad explizit übergeben. Danach enthält die kopierte
`docs/.shiller.yml` den `template_dir` für weitere Aufrufe. Ein expliziter
relativer `--template-dir`-Pfad bezieht sich auf die Projektwurzel.

Der bisherige [Seiten-API-Entwurf](docs/proposals/2026-09-12-schiller-seiten-api.md)
und die [Seitenbeispiele](examples/README.md) beschreiben den separaten
`SchillerDir`-Bereich.
