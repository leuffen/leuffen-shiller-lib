# Schiller Library

Die Library enthält den Seitenzugriff über `SchillerDir` und getrennt davon die
Installation von Theme-Vorlagen über `Leuffen\Schiller\Automation\SchillerAutomation`.

## Website aus `_tpl` anlegen

Das Theme-Paket liefert `_tpl/_root/` für die bedingungslose Grundstruktur und
weitere Vorlagen unter `_tpl/`. `init()` kopiert `_root` in das Projekt und
installiert danach Vorlagen mit mindestens einem ausgewählten Tag. `install()`
installiert nur markierte Vorlagen. Bereits vorhandene Zieldateien werden
überschrieben; zwei gleichzeitig ausgewählte Vorlagen für dasselbe Ziel sind
ein Fehler. Vor dem Kopieren werden sämtliche Ziele und Referenzen geprüft.

```php
<?php

use Leuffen\Schiller\Automation\SchillerAutomation;

$automation = new SchillerAutomation(
    projectRoot: '/srv/site',
    templateDir: '/srv/site/node_modules/@leuffen/themejs2/_tpl',
);
$written = $automation->init(['raven']);
// $written enthält relative Zielpfade, z. B. docs/index.md.
```

Markdown-Dateien unter `_tpl` werden ausgewählt, wenn ihr YAML Front Matter
einen `schiller`-Block enthält. Dieser Block bleibt in der installierten Datei
für spätere Bearbeitung erhalten:

```yaml
---
schiller:
  tags: [base, seem2]
  target: docs/index.md
  instructions:
    - ./index-anleitung.md
    - tpl:/instructions/schreibstil.md
layout: website
---
```

Andere Textdateien können als `.template` geliefert werden. Ihr erstes YAML
Front Matter enthält denselben `schiller`-Block; dieser Header wird bei der
Installation entfernt, ebenso die Dateiendung `.template`. Eine Vorlage
`navbar.osman.html.template` kann so `docs/_includes/navbar.html` erzeugen:

```yaml
---
schiller:
  tags: [theme:osman]
  target: docs/_includes/navbar.html
  instructions: tpl:/instructions/navbar.md
---
<nav>...</nav>
```

`./` verweist auf eine Anleitungsdatei neben der Quelle, `tpl:/` auf eine
Datei relativ zu `_tpl`. Anleitungen werden geprüft, aber nicht installiert.
Bei Markdown werden relative Anleitungen für spätere Verwendung nach `tpl:/`
umgeschrieben. Das Projekt muss deshalb den Template-Pfad weiterhin kennen;
die bestehende `.shiller.yml` verwendet dafür `template_dir`. Die KI-Bearbeitung
dieser Anleitungen ist noch kein Teil der Automation.

## Kommando

Composer stellt `bin/schiller` bereit. Ohne `--template-dir` wird
`template_dir` aus `.shiller.yml` im Projekt gelesen; `--root` wählt ein anderes
Projektverzeichnis als das aktuelle.

```sh
schiller init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags raven --root /srv/site
schiller install --tags raven --root /srv/site
```

Beim ersten Lauf wird der Paketpfad explizit übergeben. Danach enthält die kopierte
`.shiller.yml` den `template_dir` für weitere Aufrufe.

Der bisherige [Seiten-API-Entwurf](docs/proposals/2026-09-12-schiller-seiten-api.md)
und die [Seitenbeispiele](examples/README.md) beschreiben den separaten
`SchillerDir`-Bereich.
