<?php

use Leuffen\Shiller\Access\DTO\Document;

// Standard: nur Markdown-Seiten mit Front Matter in default_lang (de).
$tree = $site->pages();
$root = $tree->root;
$root->id; // 'index.md', sofern eine Startseite existiert

// Eine Sprache explizit auswählen: Die ID enthält den echten Sourcepfad.
$englishTree = $site->pages(language: 'en');
$englishRoot = $englishTree->root;
// Ohne en/index.md ist die Root-ID '' (virtueller Container).
// Darunter stehen ausschließlich echte Seiten, keine _includes oder HTML.
$page = $site->getPage('en/leistungen/diagnostik.md');
$page->file->path; // 'en/leistungen/diagnostik.md'

// data.path ist der Sourcepfad; data.linkUrl die aufrufbare Website-URL.
// Die Datei-ID kann direkt an getPage übergeben werden.
$node = $englishRoot->children[0] ?? $englishRoot;
$node->id;
$node->data->path;
$node->data->linkUrl;

// Als JSON für einen bestehenden HTTP-Endpunkt:
$json = json_encode($englishTree->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
