<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: keine implementierte HTTP-Route; Rückgaben sind erwartete DTOs.
// GET: $example($root); POST: $example($root, $validatedForm).
// Authentifizierung/CSRF/HTTP-Status und Formulartransport bleiben in der Anwendung.

use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\SchillerDir;
use Phore\FileSystem\PhoreDirectory;

/** @return Closure(PhoreDirectory, ?array): array */
return static function (PhoreDirectory $root, ?array $form = null): array {
    // Frische Bearbeitungseinheit je HTTP-Request; Rolle ausschließlich serverseitig.
    $site = new SchillerDir($root, access: new AccessContext(role: 'user'));
    $page = $site->getPage('/leistungen/diagnostik', language: 'de');

    $changed = false;
    if ($form !== null) {
        $previousRevision = $page->revision;
        // Der Browser hat revision aus der GET-Antwort behalten.
        // Keine Ersetzung durch $page->revision aus dem frischen POST-Ladevorgang!
        if (!isset($form['revision']) || !is_string($form['revision']) || $form['revision'] === '') {
            throw new \InvalidArgumentException('Die Revision des geladenen Formulars fehlt.');
        }

        // Nur explizit vorhandene bearbeitbare Felder anwenden.
        foreach (['title', 'short_title'] as $key) {
            if (array_key_exists($key, $form)) {
                if (!is_string($form[$key])) {
                    throw new \InvalidArgumentException($key . ' muss ein String sein.');
                }
                $page->header[$key] = $form[$key];
            }
        }
        if (array_key_exists('content', $form)) {
            if (!is_string($form['content'])) {
                throw new \InvalidArgumentException('content muss ein String sein.');
            }
            $page->content = $form['content'];
        }
        if (($form['removeShortTitle'] ?? false) === true) {
            unset($page->header['short_title']); // Ausdrückliche Löschanweisung.
        }

        // custom_tracking, ptags, weitere eigene Werte und ausgelassene Felder bleiben erhalten.
        // Konflikt: ConflictException, keine Speicherung, keine automatische Wiederholung.
        // Anwendung zeigt den Konflikt (z.B. HTTP 409) und lässt neu laden/Änderungen vergleichen.
        $page->save(expectedRevision: $form['revision']);
        $changed = $page->revision !== $previousRevision;
        // Erfolgreiches save aktualisiert revision; No-op erzeugt keine Dateiänderung.
        // Externe Änderungsmeldungen nur bei tatsächlicher erfolgreicher Mutation senden.
    }

    return [
        'id' => $page->id,
        'language' => $page->language,
        'revision' => $page->revision, // Opaquer String für den nächsten POST.
        'header' => $page->header,
        'content' => $page->content,
        'isPersisted' => $page->isPersisted(), // true
        'changed' => $changed, // GET/No-op: false; tatsächliche Speicherung: true.
        'hasChanges' => $page->hasChanges(),   // false nach GET oder erfolgreichem POST
    ];
};

// Erwarteter Ablauf: GET mit Revision A; anderer Editor speichert B;
// POST mit Revision A wirft ConflictException und überschreibt B nicht.
// Body leeren: content=''. Feld auslassen: erhalten. Entfernen: removeShortTitle=true.
// Fehlendes/gesperrtes Document: NotFoundException, kein unsicherer create-Fallback.
