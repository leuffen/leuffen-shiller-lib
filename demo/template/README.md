# Demo Template

Dieses Verzeichnis repraesentiert ein eigenstaendiges Template-Projekt.
`_tpl/_root/docs/` enthaelt die Basiskonfiguration, strukturierte Daten und
`_rules.d`; `_tpl/pages/` enthaelt die tag-basierten Markdown-Vorlagen.

`_tpl/_root/.shiller-context.d/project.md` liefert die initiale, bewusst
editierbare Vorlage fuer den zentralen Projektkontext. Rohdaten werden dagegen
erst im nutzenden Projekt unter `.shiller-context.d/raw/` abgelegt.

Wird aus `demo/project` mit dem Tag `demo` installiert, zeigt die kopierte
`.shiller.yml` zurueck auf dieses `_tpl`.
