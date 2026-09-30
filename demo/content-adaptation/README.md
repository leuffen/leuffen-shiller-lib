# Content-Adaptation Demo

Dieses Verzeichnis ist ein kleines eigenstaendiges Demo-Projekt fuer die Shiller-Content-Automation. Es enthaelt sowohl das erwartete `_tpl` als auch einen bereits installierten `docs`-Stand, Projektkontext, `.shiller.d`, eine dateispezifische Instruction und ein `_data`-Sidecar.

Vom Demo-Verzeichnis aus koennen die vorhandenen Dateien direkt verwendet werden:

```sh
php ../../bin/schiller ai adjust index.md _data/general.yml
php ../../bin/schiller revert index.md _data/general.yml
```

`ai adjust` benoetigt eine fuer `phore/ai-harness` konfigurierte AI-Verbindung; Zugangsdaten gehoeren nicht in dieses Demo-Verzeichnis. `revert` benoetigt keinen AI-Zugriff.

Die installierte `docs/.shiller.yml` verweist auf das lokale `_tpl`. `context/project.md` liefert verbindliche Projektdaten, `.shiller.d/editorial.md` ergaenzt allgemeinen Redaktionskontext, `_tpl/instructions/index.md` ist eine dateispezifische Anweisung und `docs/_data/general.yml.d.json` beschreibt die Bedeutung der strukturierten YAML-Felder.
