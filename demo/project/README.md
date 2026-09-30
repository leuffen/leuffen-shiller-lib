# Demo Project

Dieses Verzeichnis repraesentiert das einzelne Webseitenprojekt, das `../template` nutzt. Shiller wird von hier ausgefuehrt; der Document Root ist `docs/`.

Beispiele:

```sh
php ../../bin/schiller ai --debug --event edit adjust index.md _data/general.yml
php ../../bin/schiller ai --debug --event user-request adjust index.md
php ../../bin/schiller revert index.md _data/general.yml
```

`docs/_rules.d/` enthaelt allgemeine, dateispezifische, Event-gefilterte und `important` Rules. `--debug` zeigt vor dem AI-Request die tatsaechlich angewandten Rules mit Selector, Match-Anzahl, Spezifitaet, important-Status und finaler Reihenfolge. `context/project.md` und `.shiller.d/editorial.md` liefern Projektkontext; `docs/_data/general.yml.d.json` beschreibt die strukturierten Daten.
