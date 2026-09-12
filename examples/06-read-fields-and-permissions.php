<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\FieldSet;
use Leuffen\Schiller\SchillerDir;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): FieldSet {
    $site = new SchillerDir($root, access: new AccessContext(role: 'user'));
    $page = $site->getPage('leistungen/diagnostik.md');
    $fields = $page->getHeaderDefinitions();
    $shortTitle = $fields->get('short_title'); // FieldDefinition
    $published = $fields->get('published');   // FieldDefinition
    $layout = $fields->get('layout');         // FieldDefinition

    // shortTitle: key='short_title', type->value='string', maxLength=60
    // published: type->value='boolean', hasDefault=true, default=false
    // layout: type->value='select'; options: list<FieldOption>
    // options enthalten {value:'default', label:'Standard'} und
    // {value:'landing', label:'Landingpage'}.
    $actions = $site->capabilities('leistungen/diagnostik.md'); // Capabilities
    // Für user und eine vorhandene Seite:
    // read=true, write=true, createFile=false, createTranslation=false,
    // createTemplate=false, rename=true, delete=true. save() prüft selbst erneut, auch ohne UI-Prüfung.
    return $fields;
};
