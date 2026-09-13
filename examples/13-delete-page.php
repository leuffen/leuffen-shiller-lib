<?php

// Unabhängiger Schreibablauf; frische Basisfixture, löscht en und danach die Blattgruppe.
$site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
$page = $site->getPage('/leistungen/diagnostik');
$english = $page->getTranslation('en');

$english->delete(); // Sofort nur en/leistungen/diagnostik.md.
$page->getTranslation('en'); // null
$page->getTranslations()['en']->exists; // false
// Alte Referenz $english ist ungültig; save darf sie nicht wiederherstellen.

$page->delete(); // Sofort Original und alle verbleibenden Sprachdateien.
// getPage('/leistungen/diagnostik') wirft jetzt NotFoundException.
// Am Übersetzungsindex einer Kategorie löscht delete nur die Datei, niemals Kinder.
// Am Original mit Nachfahren sowie an / wird delete abgewiesen. Legacy unterstützt kein delete.
