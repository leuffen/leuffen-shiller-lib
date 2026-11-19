<?php

declare(strict_types=1);

namespace Leuffen\Shiller;

final class Codec
{
    public static function yaml(string $content, string $path = ''): array
    {
        $value = @yaml_parse($content);
        if (!is_array($value)) {
            throw new ConfigurationException("Invalid YAML: $path");
        }

        return $value;
    }

    public static function emitYaml(array $value): string
    {
        $output = yaml_emit($value, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        if (!is_string($output)) {
            throw new StorageException('Cannot encode YAML');
        }

        return preg_replace('/^---\s*\n|\.\.\.\s*\n?$/m', '', $output) ?? $output;
    }

    public static function frontMatter(string $content, string $path): array
    {
        if (!preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $content, $matches)) {
            throw new ValidationException("Front matter missing: $path");
        }

        return [self::yaml($matches[1], $path), $matches[2]];
    }

    public static function emitFrontMatter(array $header, string $content): string
    {
        return "---\n" . rtrim(self::emitYaml($header)) . "\n---\n" . $content;
    }

    public static function id(string $id): string
    {
        $id = '/' . trim($id, '/');

        return $id === '/' ? '/' : (preg_replace('#/+#', '/', $id) ?? $id);
    }
}
