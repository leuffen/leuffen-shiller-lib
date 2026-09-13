<?php

// Unabhängiger Schreibablauf; Basisfixture de/en vorhanden, fr fehlt.
$site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
$page = $site->getPage('/leistungen/diagnostik');
$documents = [];
foreach ($page->getTranslations() as $language => $info) {
    if ($info->exists) {
        $document = $page->getTranslation($language);
        $document->header['permalink'] = '/medizin/diagnostik/';
        $documents[] = $document;
    }
}
$site->saveDocuments($documents); // Ein gemeinsamer Endzustand, kein einzelnes save pro Sprache.
$page->getUrl();                       // '/medizin/diagnostik/'
$page->getTranslation('en')->getUrl(); // '/en/medizin/diagnostik/'
$page->getTranslations()['fr']->exists; // false
// Fehlende Sprachen werden nicht angelegt; verborgene/beschränkte Varianten verhindern
// eine unvollständige Gruppenumstellung. Revisionsprüfung ist keine Voraussetzung dieses Ablaufs.
// Rückkehr zu natürlichen URLs: permalink an allen Varianten unset und gemeinsam speichern.
