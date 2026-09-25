<?php

// Unabhängiger Schreibablauf mit $site aus 00; /leistungen/vorsorge fehlt.
$page = $site->createPage(
    '/leistungen/vorsorge',
    header: ['title' => 'Vorsorge'],
    content: "## Vorsorge\n",
);
$page->isPersisted(); // false
$page->save();
$page->file->path;    // 'leistungen/vorsorge.md'
$page->header['published']; // false: angelegt, noch nicht veröffentlicht

// Ergänzung in derselben Basisfixture: reine Kategorie erhält ihre eigene Indexseite.
$categoryPage = $site->createPage('/leistungen', header: ['title' => 'Leistungen']);
$categoryPage->save();
$categoryPage->file->path; // 'leistungen/index.md'; vorhandene Kinder bleiben erhalten
// Bestehende Seitengruppen werden nicht überschrieben. Legacy lehnt beide Anlagen ab.
