<?php

declare(strict_types=1);

namespace Horizom\Http\Tests\Unit;

use Horizom\Http\Exceptions\HttpException;
use Horizom\Http\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ResponseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function testDefaultStatusIs200(): void
    {
        $response = new Response();
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateFactoryMethodReturnsResponse(): void
    {
        $response = Response::create(201);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testFromInstanceCopiesAllFields(): void
    {
        $original = new Response(404, ['X-Custom' => 'value'], 'not found', '1.1', 'Not Found');
        $copy = Response::fromInstance($original);

        $this->assertSame(404, $copy->getStatusCode());
        $this->assertSame('Not Found', $copy->getReasonPhrase());
        $this->assertSame('value', $copy->getHeaderLine('X-Custom'));
        $this->assertSame('not found', (string) $copy->getBody());
    }

    // -------------------------------------------------------------------------
    // JSON
    // -------------------------------------------------------------------------

    public function testJsonEncodesDataAndSetsContentType(): void
    {
        $response = (new Response())->json(['key' => 'value']);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"key":"value"}', (string) $response->getBody());
    }

    public function testJsonSetsStatusCodeWhenProvided(): void
    {
        $response = (new Response())->json(['error' => 'bad'], 422);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testJsonDoesNotChangeStatusWhenNull(): void
    {
        $response = (new Response(200))->json(['ok' => true]);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testJsonThrowsOnUnencodableData(): void
    {
        $this->expectException(HttpException::class);

        $resource = fopen('php://memory', 'r');
        (new Response())->json($resource);
        fclose($resource);
    }

    public function testJsonIsImmutable(): void
    {
        $original = new Response();
        $new = $original->json(['a' => 1]);

        $this->assertNotSame($original, $new);
        $this->assertSame('', (string) $original->getBody());
    }

    public function testJsonWithNestedData(): void
    {
        $data = ['user' => ['name' => 'Alice', 'age' => 30]];
        $response = (new Response())->json($data);

        $this->assertSame(json_encode($data), (string) $response->getBody());
    }

    // -------------------------------------------------------------------------
    // Redirect
    // -------------------------------------------------------------------------

    public function testRedirectSetsLocationHeader(): void
    {
        $response = (new Response())->redirect('https://example.com/new');

        $this->assertSame('https://example.com/new', $response->getHeaderLine('Location'));
    }

    public function testRedirectDefaultsTo302(): void
    {
        $response = (new Response())->redirect('https://example.com/');
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testRedirectUsesProvidedStatus(): void
    {
        $response = (new Response())->redirect('https://example.com/', 301);
        $this->assertSame(301, $response->getStatusCode());
    }

    public function testRedirectWithBaseUrlPrefixesRelativeUrl(): void
    {
        $response = (new Response())->setBaseUrl('https://example.com')->redirectWithBaseUrl('dashboard');

        $this->assertStringContainsString('dashboard', $response->getHeaderLine('Location'));
    }

    public function testRedirectWithBaseUrlUsesBaseUrlWhenNoPath(): void
    {
        $response = (new Response())->setBaseUrl('https://example.com')->redirectWithBaseUrl();

        $this->assertSame('https://example.com', $response->getHeaderLine('Location'));
    }

    // -------------------------------------------------------------------------
    // toString
    // -------------------------------------------------------------------------

    public function testToStringContainsStatusLine(): void
    {
        $response = new Response(200);
        $this->assertStringContainsString('HTTP/1.1 200', (string) $response);
    }

    public function testToStringContainsBodyContent(): void
    {
        $response = (new Response())->json(['hello' => 'world']);
        $this->assertStringContainsString('{"hello":"world"}', (string) $response);
    }

    public function testToStringContainsHeaders(): void
    {
        $response = (new Response())->json(['x' => 1]);
        $this->assertStringContainsString('Content-Type: application/json', (string) $response);
    }

    // -------------------------------------------------------------------------
    // File
    // -------------------------------------------------------------------------

    public function testFileThrowsOnInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Response())->file(42);  // @phpstan-ignore-line
    }

    public function testFileWithStringPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'horizom_test_');
        file_put_contents($path, 'file content');

        $response = (new Response())->file($path);

        $this->assertSame('file content', (string) $response->getBody());

        unlink($path);
    }

    public function testFileWithResource(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'resource content');
        rewind($resource);

        $response = (new Response())->file($resource);

        $this->assertSame('resource content', (string) $response->getBody());
        fclose($resource);
    }

    // -------------------------------------------------------------------------
    // Download
    // -------------------------------------------------------------------------

    public function testDownloadSetsContentDispositionHeader(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'horizom_dl_');
        file_put_contents($path, 'data');

        $response = (new Response())->download($path);

        $this->assertStringContainsString('attachment', $response->getHeaderLine('Content-Disposition'));

        unlink($path);
    }

    public function testDownloadUsesProvidedFileName(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'horizom_dl_');
        file_put_contents($path, 'data');

        $response = (new Response())->download($path, 'report.pdf');

        $this->assertStringContainsString('report.pdf', $response->getHeaderLine('Content-Disposition'));

        unlink($path);
    }

    // -------------------------------------------------------------------------
    // setBaseUrl
    // -------------------------------------------------------------------------

    public function testSetBaseUrlReturnsSameInstance(): void
    {
        $response = new Response();
        $result = $response->setBaseUrl('https://example.com');
        $this->assertSame($response, $result);
    }
}
