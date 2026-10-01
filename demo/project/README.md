# Demo Project

Dieses Verzeichnis repraesentiert das einzelne Webseitenprojekt, das
`../template` nutzt. Shiller wird von hier ausgefuehrt; der Document Root ist
`docs/`.

Beispiele:

```sh
php ../../bin/schiller context build
php ../../bin/schiller context build .shiller-context.d/raw --focus "Kontaktdaten"
php ../../bin/schiller ai --debug --event edit adjust index.md _data/general.yml
php ../../bin/schiller ai --debug --event user-request adjust index.md
php ../../bin/schiller revert index.md _data/general.yml
```

`.shiller-context.d/project.md` ist der zentrale Projektkontext. Weitere
Markdown-Dateien direkt in `.shiller-context.d/` werden ebenfalls immer als
Kontext geladen. `.shiller-context.d/raw/` enthaelt Rohdaten fuer
`context build` und wird bei `ai adjust` nicht direkt eingebunden.

`docs/_rules.d/` enthaelt allgemeine, dateispezifische, Event-gefilterte und
`important` Rules. `--debug` zeigt vor dem AI-Request die angewandten Rules
mit Selector, Match-Anzahl, Spezifitaet, important-Status und finaler
Reihenfolge. `docs/_data/general.yml.d.json` beschreibt die strukturierten
Daten.
