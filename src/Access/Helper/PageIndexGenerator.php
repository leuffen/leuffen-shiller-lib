<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Helper;

use Leuffen\Shiller\Access\DTO\TreeNode;
use Leuffen\Shiller\Access\ShillerRepository;

/** @internal Generates the multilingual page index using the existing Shiller adapter. */
final class PageIndexGenerator
{
    /**
     * Build a CSV of existing Markdown pages, including unpublished variants.
     *
     * @param ShillerRepository $site Bound website repository.
     * @return string CSV with file, title, url, published and language columns.
     * @throws \Leuffen\Shiller\Access\Exception\ShillerException On invalid sources.
     * @example $csv = (new PageIndexGenerator())->generate($site);
     * @see ShillerRepository::pages()
     */
    public function generate(ShillerRepository $site): string
    {
        $rows = [];
        $adapter = $site->adapter();

        // Der Adapter kennt die Sprachzuordnung und die tatsaechlichen URLs.
        $visit = function (TreeNode $node) use (&$visit, &$rows, $adapter): void {
            foreach ($node->data->translations as $translation) {
                if (!$translation->exists || !str_ends_with(strtolower($translation->path), '.md')) {
                    continue;
                }
                $document = $adapter->load($node->id, $translation->language);
                $header = $adapter->getEffectiveHeader($document);
                $title = $header['title'] ?? $node->label;
                $rows[$translation->path] = [
                    $translation->path,
                    is_string($title) ? $title : $node->label,
                    $translation->published ? $adapter->getUrl($document) : '',
                    $translation->published ? 'true' : 'false',
                    $translation->language,
                ];
            }
            foreach ($node->children as $child) {
                $visit($child);
            }
        };
        $visit($site->pages()->root);

        // CSV-Werte konsequent quoten, auch bei Kommas und Zeilenumbruechen.
        ksort($rows);
        $lines = ['file,title,url,published,language'];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                static fn(string $value): string => '"' . str_replace('"', '""', $value) . '"',
                $row,
            ));
        }
        return implode("\n", $lines) . "\n";
    }
}
