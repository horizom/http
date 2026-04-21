<?php

declare(strict_types=1);

namespace Horizom\Http;

use Horizom\Http\Exceptions\FileNotFoundException;

class UploadedFile extends \Symfony\Component\HttpFoundation\File\UploadedFile
{
    use FileHelpers;

    /**
     * Get the contents of the uploaded file.
     *
     * @return false|string
     *
     * @throws FileNotFoundException
     */
    public function get(): string
    {
        if (!$this->isValid()) {
            throw new FileNotFoundException("File does not exist at path {$this->getPathname()}.");
        }

        $contents = file_get_contents($this->getPathname());
        if ($contents === false) {
            throw new FileNotFoundException("Unable to read file at path {$this->getPathname()}.");
        }

        return $contents;
    }

    /**
     * Get the file's extension supplied by the client.
     *
     * @return string
     */
    public function clientExtension(): string
    {
        return $this->guessClientExtension() ?? '';
    }

    /**
     * Create a new file instance from a base instance.
     *
     * @param  UploadedFile  $file
     * @param  bool  $test
     * @return static
     */
    public static function createFromBase(UploadedFile $file, bool $test = false): static
    {
        return $file instanceof static ? $file : new static(
            $file->getPathname(),
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            $file->getError(),
            $test
        );
    }

    /**
     * Parse and format the given options.
     *
     * @param array<string, mixed>|string $options
     * @return array<string, mixed>
     */
    protected function parseOptions(array|string $options): array
    {
        if (is_string($options)) {
            $options = ['disk' => $options];
        }

        return $options;
    }
}
