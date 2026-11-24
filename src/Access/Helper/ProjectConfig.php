<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Helper;

use Leuffen\Shiller\Access\Adapter\Adapter;
use Leuffen\Shiller\Access\Adapter\V1_0\JekyllLegacyAdapter;
use Leuffen\Shiller\Access\Adapter\V1_0\JekyllPolyglotAdapter;
use Leuffen\Shiller\Access\Exception\ConfigurationException;

/** Validiert die Projektkonfiguration und registriert konkrete Adapterversionen. */
final class ProjectConfig
{
    private const ADAPTERS = [
        'legacy:1.0' => JekyllLegacyAdapter::class,
        'polyglot:1.0' => JekyllPolyglotAdapter::class,
    ];

    /** @return array<string, mixed> */
    public static function validate(mixed $config, string $path = '.shiller.yml'): array
    {
        if (!is_array($config)) {
            throw new ConfigurationException("Invalid Shiller config: $path");
        }

        $version = $config['version'] ?? null;
        if ($version !== '1.0' && $version !== 1.0) {
            throw new ConfigurationException("Invalid or missing version in $path; expected 1.0");
        }

        self::adapterClass($config['adapter'] ?? null, $path);

        return $config;
    }

    /** @return class-string<Adapter> */
    public static function adapterClass(mixed $id, string $path = '.shiller.yml'): string
    {
        if (!is_string($id) || !isset(self::ADAPTERS[$id])) {
            throw new ConfigurationException(
                "Invalid or missing adapter in $path; expected one of: " . implode(', ', array_keys(self::ADAPTERS)),
            );
        }

        return self::ADAPTERS[$id];
    }
}
