# Demo Template

Dieses Verzeichnis repraesentiert ein eigenstaendiges Template-Projekt.
`_tpl/_root/docs/` enthaelt Basiskonfiguration und strukturierte Daten;
`_tpl/pages/` enthaelt tag-basierte Markdown-Vorlagen. Die AI-Anpassung
verwendet die Skills aus dem installierten ThemeJS2-npm-Paket.

`_tpl/_root/.shiller-context.d/project.md` liefert die initiale, bewusst
editierbare Vorlage fuer den zentralen Projektkontext. Rohdaten werden dagegen
erst im nutzenden Projekt unter `.shiller-context.d/raw/` abgelegt.

Wird aus `demo/project` mit dem Tag `demo` installiert, zeigt die kopierte
`.shiller.yml` zurueck auf dieses `_tpl`.
