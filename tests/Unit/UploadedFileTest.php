<?php

declare(strict_types=1);

namespace Horizom\Http\Tests\Unit;

use Horizom\Http\Exceptions\FileNotFoundException;
use Horizom\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

final class UploadedFileTest extends TestCase
{
    private function makeTempFile(string $content = 'test content'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'horizom_uf_');
        file_put_contents($path, $content);
        return $path;
    }

    private function makeUploadedFile(string $path, string $name = 'test.txt', int $error = UPLOAD_ERR_OK): UploadedFile
    {
        return new UploadedFile($path, $name, 'text/plain', $error, true);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function testGetReturnsFileContents(): void
    {
        $path = $this->makeTempFile('hello world');
        $file = $this->makeUploadedFile($path);

        $this->assertSame('hello world', $file->get());

        unlink($path);
    }

    public function testGetThrowsWhenFileIsInvalid(): void
    {
        $path = $this->makeTempFile();
        $file = $this->makeUploadedFile($path, 'test.txt', UPLOAD_ERR_PARTIAL);

        $this->expectException(FileNotFoundException::class);
        $file->get();

        unlink($path);
    }

    // -------------------------------------------------------------------------
    // clientExtension()
    // -------------------------------------------------------------------------

    public function testClientExtensionGuessesFromClientMimeType(): void
    {
        $path = $this->makeTempFile();
        $file = new UploadedFile($path, 'photo.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);

        $this->assertSame('jpg', $file->clientExtension());

        unlink($path);
    }

    // -------------------------------------------------------------------------
    // createFromBase()
    // -------------------------------------------------------------------------

    public function testCreateFromBaseReturnsSameInstanceIfAlreadyCorrectType(): void
    {
        $path = $this->makeTempFile();
        $file = $this->makeUploadedFile($path);

        $result = UploadedFile::createFromBase($file);

        $this->assertSame($file, $result);

        unlink($path);
    }

    // -------------------------------------------------------------------------
    // FileHelpers trait (via UploadedFile)
    // -------------------------------------------------------------------------

    public function testHashNameReturnsStringWith40RandomChars(): void
    {
        $path = $this->makeTempFile();
        $file = $this->makeUploadedFile($path);

        $hash = $file->hashName();

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{40}\..+$/', $hash);

        unlink($path);
    }

    public function testHashNameIsCached(): void
    {
        $path = $this->makeTempFile();
        $file = $this->makeUploadedFile($path);

        $this->assertSame($file->hashName(), $file->hashName());

        unlink($path);
    }

    public function testHashNamePrependsPath(): void
    {
        $path = $this->makeTempFile();
        $file = $this->makeUploadedFile($path);

        $result = $file->hashName('/uploads');

        $this->assertStringStartsWith('/uploads/', $result);

        unlink($path);
    }

    public function testExtensionReturnsGuessedExtension(): void
    {
        $path = $this->makeTempFile();
        $file = new UploadedFile($path, 'image.png', 'image/png', UPLOAD_ERR_OK, true);

        $this->assertNotEmpty($file->extension());

        unlink($path);
    }
}
