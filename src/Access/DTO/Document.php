<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\DTO;

use Leuffen\Shiller\Access\ShillerRepository;
use Leuffen\Shiller\Access\Adapter\Adapter;
use Leuffen\Shiller\Access\Exception\ValidationException;

final class Document
{
    private ?ShillerRepository $site = null;
    private array $originalHeader;
    private string $originalContent;

    public function __construct(
        public string $id,
        public readonly string $language,
        public readonly bool $isRootDocument,
        public ?FileEntry $file,
        public array $header,
        public string $content,
        public array $adapterState = [],
        private bool $persisted = true,
    ) {
        $this->originalHeader = $header;
        $this->originalContent = $content;
    }

    public function attach(ShillerRepository $site): self
    {
        $this->site = $site;

        return $this;
    }

    private function site(): ShillerRepository
    {
        return $this->site ?? throw new \LogicException('Document is not attached');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'language' => $this->language,
            'isRootDocument' => $this->isRootDocument,
            'file' => $this->file?->toArray(),
            'header' => $this->header,
            'content' => $this->content,
            'adapterState' => $this->adapterState,
            'persisted' => $this->persisted,
        ];
    }

    public function getEffectiveHeader(): array
    {
        return $this->site()->adapter()->getEffectiveHeader($this);
    }

    public function getHeaderDefinitions(): FieldSet
    {
        return $this->site()->getHeaderDefinitions($this->id, $this->language);
    }

    /**
     * Liefert die vom Adapter für dieses Dokument sichtbaren Form-Felder.
     *
     * Der aktuelle Header wird nur gelesen; nicht gesetzte Werte werden über
     * den Default angezeigt, aber nicht automatisch ins Dokument geschrieben.
     *
     * @return list<array<string, mixed>>
     * @see \Leuffen\Shiller\Access\Adapter\Adapter::getEditableHeaders()
     * @example $fields = $site->getPage('/leistungen')->getEditableHeaders();
     */
    public function getEditableHeaders(): array
    {
        return $this->site()->adapter()->getEditableHeaders($this);
    }

    public function getTranslation(?string $language = null, bool $createIfMissing = false): ?Document
    {
        return $this->site()->translation($this, $language, $createIfMissing);
    }

    public function getTranslations(): array
    {
        return $this->site()->translations($this);
    }

    public function getUrl(bool $absolute = false): string
    {
        return $this->site()->adapter()->getUrl($this, $absolute);
    }

    public function isPersisted(): bool
    {
        return $this->persisted;
    }

    public function hasChanges(): bool
    {
        return $this->header !== $this->originalHeader
            || $this->content !== $this->originalContent;
    }

    public function markPersisted(?FileEntry $file = null): void
    {
        $this->persisted = true;

        if ($file) {
            $this->file = $file;
        }

        $this->originalHeader = $this->header;
        $this->originalContent = $this->content;
    }

    /**
     * Übernimmt einen partiellen Header- und optionalen Content-Patch.
     *
     * Nur gesendete Top-Level-Header werden ersetzt; null entfernt den
     * jeweiligen Schlüssel und setzt damit auf einen möglichen Default zurück.
     * Nicht gesendete Header, Identität und Adapterzustand bleiben erhalten.
     * Die Identitätsheader pid/lang sind auch als null nicht patchbar.
     * Content null erhält den bisherigen Text, ein leerer String leert ihn.
     * Die Methode verändert nur dieses Dokument; erst save() persistiert es.
     *
     * @param array<string, mixed> $header Gesendete Headerwerte, null entfernt.
     * @param string|null $content Neuer Inhalt oder null zum Beibehalten.
     * @return self Dieses bearbeitete Dokument zur anschließenden Speicherung.
     * @throws ValidationException Wenn pid oder lang im Header-Patch enthalten ist.
     * @example $page->patch(['title' => 'Neu', 'layout' => null])->save();
     * @see self::save()
     * @see ShillerRepository::getDocumentByPath()
     */
    public function patch(array $header = [], ?string $content = null): self
    {
        // Identitätsänderungen vor jeder Mutation des Dokuments zurückweisen.
        foreach (['pid', 'lang'] as $key) {
            if (array_key_exists($key, $header)) {
                throw new ValidationException("Identity header cannot be patched: $key");
            }
        }

        foreach ($header as $key => $value) {
            if ($value === null) {
                unset($this->header[$key]);
            } else {
                $this->header[$key] = $value;
            }
        }
        if ($content !== null) {
            $this->content = $content;
        }
        return $this;
    }

    public function save(): void
    {
        $this->site()->saveDocuments([$this]);
    }

    public function rename(string $id): void
    {
        $this->site()->rename($this->id, $id);
    }

    public function delete(): void
    {
        $this->site()->adapter()->delete($this);
    }
}
