# phore-project-template
Template Repository for phore library projects

## Git Submodules

Beim Klonen direkt mit auschecken:

```bash
git clone --recurse-submodules <repo-url>
```

Nachträglich initialisieren oder aktualisieren:

```bash
git submodule update --init --recursive
git submodule update --remote --merge
```



## Schiller-Entwurf

- [API-Entwurf](docs/proposals/2026-09-12-schiller-seiten-api.md) und [PHP-Beispiele](examples/README.md).
- [TreeNode-Konvention v1](docs/tree-node.md): wiederverwendbares Baumformat mit id, label, children und data; an MUI Rich Tree View angelehnt, zugängliche Darstellung nach WAI-ARIA.

Diese API ist noch nicht implementiert.
