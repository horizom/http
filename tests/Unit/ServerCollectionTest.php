<?php

declare(strict_types=1);

namespace Horizom\Http\Tests\Unit;

use Horizom\Http\Collection\ServerCollection;
use PHPUnit\Framework\TestCase;

final class ServerCollectionTest extends TestCase
{
    private function make(array $data): ServerCollection
    {
        return new ServerCollection($data);
    }

    public function testGetHeadersExtractsHttpPrefixedKeys(): void
    {
        $col = $this->make(['HTTP_HOST' => 'example.com', 'HTTP_ACCEPT' => 'text/html']);
        $headers = $col->getHeaders();

        $this->assertArrayHasKey('HOST', $headers);
        $this->assertArrayHasKey('ACCEPT', $headers);
        $this->assertSame('example.com', $headers['HOST']);
    }

    public function testGetHeadersExtractsNonPrefixedHeaders(): void
    {
        $col = $this->make(['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => '123']);
        $headers = $col->getHeaders();

        $this->assertArrayHasKey('CONTENT_TYPE', $headers);
        $this->assertSame('application/json', $headers['CONTENT_TYPE']);
    }

    public function testGetHeadersExcludesNonHeaderServerKeys(): void
    {
        $col = $this->make(['DOCUMENT_ROOT' => '/var/www', 'HTTP_HOST' => 'example.com']);
        $headers = $col->getHeaders();

        $this->assertArrayNotHasKey('DOCUMENT_ROOT', $headers);
        $this->assertArrayHasKey('HOST', $headers);
    }

    public function testHasPrefixReturnsTrueForMatch(): void
    {
        $this->assertTrue(ServerCollection::hasPrefix('HTTP_HOST', 'HTTP_'));
        $this->assertFalse(ServerCollection::hasPrefix('SERVER_NAME', 'HTTP_'));
    }
}
