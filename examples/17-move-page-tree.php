<?php

declare(strict_types=1);

// ENTWURF: Polyglot, eigene Fixture:
// leistungen.md, en/leistungen.md (Zieleltern bisher Blätter)
// vorsorge/index.md, vorsorge/kinder.md, vorsorge/bild.png
// en/vorsorge/index.md, en/vorsorge/kinder.md; fr fehlt.
use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\Document;
use Leuffen\Schiller\SchillerDir;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): Document {
    $site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
    $parent = $site->getPage('/leistungen');
    $category = $site->getPage('/vorsorge');
    $child = $site->getPage('/vorsorge/kinder');
    $englishChild = $child->getTranslation('en');

    $category->rename('/leistungen/vorsorge'); // Sofort verschieben, kein save nötig.

    assert($parent->id === '/leistungen'); // Zieleltern-ID bleibt erhalten.
    assert($parent->file->path === 'leistungen/index.md');
    assert($parent->getTranslation('en')->file->path === 'en/leistungen/index.md');
    assert($category->id === '/leistungen/vorsorge');
    assert($category->file->path === 'leistungen/vorsorge/index.md');
    assert($child->id === '/leistungen/vorsorge/kinder');
    assert($child->file->path === 'leistungen/vorsorge/kinder.md');
    assert($englishChild->file->path === 'en/leistungen/vorsorge/kinder.md');
    // Auch bild.png und alle weiteren untergeordneten Dateien sind mit verschoben.
    // en/vorsorge/* wurde vollständig nach en/leistungen/vorsorge/* verschoben.
    // fr wird nicht erfunden. Header/Body und eigene Metadaten bleiben unverändert.
    // Vorhandenes Ziel, Verschieben in sich selbst, Rechtefehler => keine Änderung.
    // Gleicher Mechanismus: rename('/checkups') benennt den ganzen Teilbaum um.
    // Reine Kategorie ohne Document: $site->rename('/wissen', '/leistungen/wissen').
    return $category;
};
