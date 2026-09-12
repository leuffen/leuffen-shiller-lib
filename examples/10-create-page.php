<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: nur im neuen Polyglot-Adapter; Schreiben erst bei save().
use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\Document;
use Leuffen\Schiller\SchillerDir;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): Document {
    $site = new SchillerDir($root, access: new AccessContext(role: 'user'));
    $page = $site->createPage(
        '/leistungen/vorsorge',
        header: ['title' => 'Vorsorge', 'published' => false, 'custom_flag' => true],
        content: "## Vorsorge\n",
    );
    assert($page->id === '/leistungen/vorsorge');
    assert($page->file === null);
    assert(!$page->isPersisted());
    assert($page->getTranslation() === $page);

    $page->save(); // Adapter legt Ordner an/ordnet Eltern bei Bedarf als Index ein.
    assert($page->isPersisted());
    assert($page->file->path === 'leistungen/vorsorge.md');
    // Neuer Blattknoten; eigener Header custom_flag bleibt erhalten.
    // Elternumstellung: Beispiel 16. Legacy: createPage wirft UnsupportedOperationException.
    return $page;
};
