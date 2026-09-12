<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\SiteConfig;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): SiteConfig {
    $site = new SchillerDir($root);
    $config = $site->config();

    // Erwartete Werte für die Fixture aus Proposal § 6/7:
    // $config->languages       === ['de', 'en', 'fr']
    // $config->defaultLanguage === 'de'
    // $config->url             === 'https://example.org'
    // $config->baseurl         === '/praxis'
    // $config->adapter->id     === 'jekyll-polyglot'
    // $config->adapter->version === 1
    // config() prüft bei einem späteren Aufruf den aktuellen Dateistand erneut.
    return $config;
};
