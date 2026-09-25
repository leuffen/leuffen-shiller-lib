<?php

// Unabhängiger Schreibablauf mit $site aus 00 und frischer Basisfixture; fr fehlt.
$page = $site->getPage('/leistungen/diagnostik');
$french = $page->getTranslation('fr', createIfMissing: true);
if (!$french->isPersisted()) {
    // Originalkopie: deutscher Body/eigene Header, published=false; keine maschinelle Übersetzung.
    $french->file; // null
    $page->getTranslation('fr') === $french; // true: derselbe vorbereitete Entwurf
    $page->getTranslations()['fr']->exists;  // false: noch keine Datei

    $french->header['title'] = 'Diagnostic';
    $french->content = "## Diagnostic\n\nTexte français.\n";
    $french->save();
}
$french->isPersisted(); // true
$french->file->path;    // 'fr/leistungen/diagnostik.md'
// Eine vorhandene Variante wird unverändert zurückgegeben und hier nicht überschrieben.
// Legacy erlaubt keine Anlage: fehlend + createIfMissing wirft UnsupportedOperationException.
