<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: keine implementierte Schiller-Laufzeit.
use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\PageTree;
use Leuffen\Schiller\TreeNode;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): PageTree {
    $site = new SchillerDir($root);
    $tree = $site->pages('/leistungen');
    // root.id='/leistungen', root.path=null, children: list<TreeNode>.
    // Basisfixture: Kategorie ohne index.md => file=null, getDocument()=null.
    // Mit Index: file.path='leistungen/index.md'; kein zusätzlicher Index-Knoten.
    // Dieselbe ID wäre auch für eine alleinige leistungen.md gültig.
    // file=null: nur aufklappen, kein Seitenlink/Editor; metadata bleibt verfügbar.

    $visit = static function (TreeNode $node) use (&$visit): void {
        $id = $node->id; // z.B. '/leistungen/diagnostik'; keine Endung oder Sprache.
        $metadata = $node->metadata; // z.B. Legacy-Kategoriebeschreibung; sonst [].
        foreach ($node->translations as $language => $info) {
            // de/en vorhanden, fr fehlt: alle lesbaren Sprachen mit exists.
            // info.path ist eine interne Quellreferenz, kein Navigationsschlüssel.
            $exists = $info->exists;
            $published = $info->published; // true/false bei Bestand, null bei fehlender Datei.
            // Statusanzeige braucht keinen vollständigen Body.
        }
        $document = $node->getDocument();
        if ($document !== null) {
            $url = $document->getUrl(); // '/leistungen/diagnostik.html'
            $english = $document->getTranslation('en');
            // Navigation/Editor verwenden document.id und language.
        }
        $leaf = $node->isLeaf(); // Nur Kinderlosigkeit; unabhängig von eigener Seite.
        foreach ($node->children as $child) {
            $visit($child);
        }
    };
    $visit($tree->root);
    return $tree;
};
