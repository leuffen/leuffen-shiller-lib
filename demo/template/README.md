# Demo Template

Dieses Verzeichnis repraesentiert ein eigenstaendiges Template-Projekt.
`_tpl/_root/.shiller.yml` enthaelt die Projektkonfiguration mit
`version: 1.0` und `adapter: polyglot:1.0`;
`_tpl/_root/docs/` enthaelt strukturierte Daten;
`_tpl/pages/` enthaelt tag-basierte Markdown-Vorlagen. Die AI-Anpassung
verwendet die Skills aus dem installierten ThemeJS2-npm-Paket.

`_tpl/_root/.shiller-context.d/project.md` liefert die initiale, bewusst
editierbare Vorlage fuer den zentralen Projektkontext. Rohdaten werden dagegen
erst im nutzenden Projekt unter `.shiller-context.d/raw/` abgelegt.

Bei Installation nach `demo/project` zeigt die kopierte `.shiller.yml`
mit `template_dir: ../template/_tpl` auf das benachbarte Demo-Template.
Dieser relative Pfad gilt nur fuer diese Demo-Verzeichnisstruktur.
