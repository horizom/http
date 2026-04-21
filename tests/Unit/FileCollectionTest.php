<?php

declare(strict_types=1);

namespace Horizom\Http\Tests\Unit;

use Horizom\Http\Collection\FileCollection;
use Horizom\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

final class FileCollectionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Basic operations
    // -------------------------------------------------------------------------

    public function testEmptyCollectionHasNoFiles(): void
    {
        $col = new FileCollection([]);
        $this->assertCount(0, $col);
    }

    public function testAcceptsUploadedFileInstance(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'horizom_uf_');
        file_put_contents($path, 'test');

        $file = new UploadedFile($path, 'test.txt', 'text/plain', UPLOAD_ERR_OK, true);
        $col = new FileCollection(['doc' => $file]);

        $this->assertInstanceOf(UploadedFile::class, $col->get('doc'));

        unlink($path);
    }

    public function testConvertsPhpFilesArrayToUploadedFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'horizom_uf_');
        file_put_contents($path, 'data');

        $filesArray = [
            'avatar' => [
                'name' => 'photo.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => $path,
                'error' => UPLOAD_ERR_OK,
                'size' => 4,
            ],
        ];

        $col = new FileCollection($filesArray);

        $this->assertTrue($col->has('avatar'));
        $this->assertInstanceOf(UploadedFile::class, $col->get('avatar'));

        unlink($path);
    }

    public function testFilesWithNoUploadErrorAreSkipped(): void
    {
        $filesArray = [
            'doc' => [
                'name' => '',
                'type' => '',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
        ];

        $col = new FileCollection($filesArray);
        $this->assertNull($col->get('doc'));
    }

    public function testSetThrowsForInvalidValue(): void
    {
        $col = new FileCollection([]);
        $this->expectException(\InvalidArgumentException::class);
        $col->set('file', 'not-a-file');  // @phpstan-ignore-line
    }

    public function testReplaceResetsCollection(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'horizom_uf_');
        file_put_contents($path, 'x');

        $file = new UploadedFile($path, 'a.txt', 'text/plain', UPLOAD_ERR_OK, true);
        $col = new FileCollection(['a' => $file]);

        $col->replace([]);

        $this->assertCount(0, $col);

        unlink($path);
    }

    public function testHasReturnsTrueForExistingFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'horizom_uf_');
        file_put_contents($path, 'x');

        $file = new UploadedFile($path, 'test.txt', 'text/plain', UPLOAD_ERR_OK, true);
        $col = new FileCollection(['upload' => $file]);

        $this->assertTrue($col->has('upload'));
        $this->assertFalse($col->has('nonexistent'));

        unlink($path);
    }

    // -------------------------------------------------------------------------
    // Multi-file upload (PHP array-style $_FILES)
    // -------------------------------------------------------------------------

    public function testConvertsMultiFileUploadArray(): void
    {
        $path1 = tempnam(sys_get_temp_dir(), 'horizom_uf_');
        $path2 = tempnam(sys_get_temp_dir(), 'horizom_uf_');
        file_put_contents($path1, 'file1');
        file_put_contents($path2, 'file2');

        $filesArray = [
            'docs' => [
                'name' => ['doc1.txt', 'doc2.txt'],
                'type' => ['text/plain', 'text/plain'],
                'tmp_name' => [$path1, $path2],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [5, 5],
            ],
        ];

        $col = new FileCollection($filesArray);
        $files = $col->get('docs');

        $this->assertIsArray($files);
        $this->assertCount(2, $files);
        $this->assertInstanceOf(UploadedFile::class, $files[0]);
        $this->assertInstanceOf(UploadedFile::class, $files[1]);

        unlink($path1);
        unlink($path2);
    }
}
