<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\PageTree;
use Leuffen\Schiller\TreeNode;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): PageTree {
    $site = new SchillerDir($root);
    $tree = $site->pages('leistungen');

    // root: TreeNode, path='leistungen', children: list<TreeNode>.
    // In der Basisfixture hat leistungen/ keine index.md:
    // root.file=null, root.translations=[], root.isLeaf()=false.
    // Mit leistungen/index.md wäre diese Seite direkt dem root-Knoten zugeordnet;
    // sie wäre kein zusätzlicher index.md-Kindknoten.
    // file=null: kein Editor/Seitenlink; Kinder dürfen aufgeklappt werden.
    $categoryPage = $tree->root->getDocument(); // ?Document
    // Ohne Index: null. Mit Index: Document mit path='leistungen/index.md'.

    $visit = static function (TreeNode $node) use (&$visit): void {
        foreach ($node->translations as $language => $info) {
            // Diagnostik-Knoten, array<string, TranslationInfo>:
            // de: path='leistungen/diagnostik.md', exists=true, isRootDocument=true
            // en: path='en/leistungen/diagnostik.md', exists=true, isRootDocument=false
            // fr: path='fr/leistungen/diagnostik.md', exists=false, isRootDocument=false
            // Alle konfigurierten lesbaren Sprachen; verborgene Varianten fehlen.
            $exists = $info->exists;
        }

        $document = $node->getDocument(); // Document bei Seiten, sonst null
        if ($document !== null) {
            $published = $document->getEffectiveHeader()['published'] ?? true;
            $url = $document->getUrl();
            // Diagnostik: published=true, url='/leistungen/diagnostik.html'.
            // Eine Sprachvariante bei Bedarf: $document->getTranslation('en').
        }

        $isLeaf = $node->isLeaf(); // Kinderlos; unabhängig von der eigenen Seite.
        foreach ($node->children as $child) {
            $visit($child);
        }
    };
    $visit($tree->root);

    // $node->file: ?FileEntry, z.B. path=leistungen/diagnostik.md, kind=page.
    // Im Legacy-Profil könnte derselbe logische Knoten auf diagnostik.de.md zeigen.
    // Gleicher TreeNode-Typ für Kategorien, Seiten und physische Dateiknoten.
    // published=false bleibt sichtbar, sofern lesbar; Fehler in tree->diagnostics.
    return $tree;
};
