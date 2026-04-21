<?php

declare(strict_types=1);

namespace Horizom\Http\Tests\Unit;

use Horizom\Http\Exceptions\HttpException;
use Horizom\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private function makeRequest(
        string $method = 'GET',
        string $uri = 'https://example.com/foo/bar',
        array $headers = [],
        ?string $body = null,
        array $serverParams = []
    ): Request {
        return new Request($method, $uri, $headers, $body, '1.1', $serverParams);
    }

    // -------------------------------------------------------------------------
    // Constructor & basic accessors
    // -------------------------------------------------------------------------

    public function testPathReturnsUriPath(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/foo/bar');
        $this->assertSame('/foo/bar', $req->path());
    }

    public function testMethodReturnsMethodAsStored(): void
    {
        $req = $this->makeRequest('POST', 'https://example.com/');
        $this->assertSame('POST', $req->method());
    }

    public function testIsMethodReturnsTrueForMatchingMethod(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->assertTrue($req->isMethod('GET'));
        $this->assertTrue($req->isMethod('get'));
        $this->assertFalse($req->isMethod('POST'));
    }

    // -------------------------------------------------------------------------
    // URL helpers
    // -------------------------------------------------------------------------

    public function testUrlReturnsUrlWithoutQueryString(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/path?foo=bar');
        $this->assertSame('https://example.com/path', $req->url());
    }

    public function testFullUrlIncludesQueryString(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/path?foo=bar&baz=1');
        $this->assertSame('https://example.com/path?foo=bar&baz=1', $req->fullUrl());
    }

    public function testBaseUrlAndBasePathAfterSetBasePath(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/app/users');
        $req->setBasePath('/app');

        $this->assertSame('/app', $req->basePath());
        $this->assertSame('https://example.com/app', $req->baseUrl());
    }

    public function testRootStripsTrailingSlash(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->assertSame('https://example.com', $req->root());
    }

    // -------------------------------------------------------------------------
    // Header helpers
    // -------------------------------------------------------------------------

    public function testHeaderReturnsSingleHeaderValue(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['X-Custom' => 'hello']);
        $this->assertSame('hello', $req->header('X-Custom'));
    }

    public function testHeaderReturnsDefaultWhenMissing(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->assertSame('default', $req->header('X-Missing', 'default'));
        $this->assertNull($req->header('X-Missing'));
    }

    public function testHeadersReturnsAllHeaders(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['X-Foo' => 'bar']);
        $this->assertArrayHasKey('X-Foo', $req->headers());
    }

    // -------------------------------------------------------------------------
    // Bearer token
    // -------------------------------------------------------------------------

    public function testBearerTokenExtractsToken(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['Authorization' => 'Bearer abc123']);
        $this->assertSame('abc123', $req->bearerToken());
    }

    public function testBearerTokenReturnsNullWhenAbsent(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->assertNull($req->bearerToken());
    }

    public function testBearerTokenReturnsNullForBasicAuth(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['Authorization' => 'Basic dXNlcjpwYXNz']);
        $this->assertNull($req->bearerToken());
    }

    public function testBearerTokenTrimsWhitespace(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['Authorization' => 'Bearer  spaced ']);
        $this->assertSame('spaced', $req->bearerToken());
    }

    // -------------------------------------------------------------------------
    // User agent
    // -------------------------------------------------------------------------

    public function testUserAgentReturnsString(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['User-Agent' => 'TestBot/1.0']);
        $this->assertSame('TestBot/1.0', $req->userAgent());
    }

    public function testUserAgentReturnsNullWhenAbsent(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->assertNull($req->userAgent());
    }

    // -------------------------------------------------------------------------
    // IP address
    // -------------------------------------------------------------------------

    public function testIpReturnsCloudflareHeaderFirst(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['CF-Connecting-IP' => '1.2.3.4']);
        $this->assertSame('1.2.3.4', $req->ip());
    }

    public function testIpReturnsFirstValueFromXForwardedFor(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['X-Forwarded-For' => '10.0.0.1, 192.168.1.1']);
        $this->assertSame('10.0.0.1', $req->ip());
    }

    public function testIpFallsBackToServerParams(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', [], null, ['REMOTE_ADDR' => '127.0.0.1']);
        $this->assertSame('127.0.0.1', $req->ip());
    }

    public function testIpReturnsNullWhenNotSet(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->assertNull($req->ip());
    }

    // -------------------------------------------------------------------------
    // HTTPS / secure
    // -------------------------------------------------------------------------

    public function testIsSecureReturnsTrueForHttpsScheme(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', [], null, ['HTTPS' => 'on']);
        $this->assertTrue($req->isSecure());
        $this->assertTrue($req->secure());
    }

    public function testIsSecureReturnsTrueForXForwardedProtoHttps(): void
    {
        $req = $this->makeRequest('GET', 'http://example.com/', ['X-Forwarded-Proto' => 'https']);
        $this->assertTrue($req->isSecure());
    }

    public function testIsSecureReturnsFalseForHttp(): void
    {
        $req = $this->makeRequest('GET', 'http://example.com/', [], null, ['HTTPS' => 'off']);
        $this->assertFalse($req->isSecure());
    }

    public function testIsSecureReturnsTrueForPort443(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', [], null, ['SERVER_PORT' => '443']);
        $this->assertTrue($req->isSecure());
    }

    // -------------------------------------------------------------------------
    // AJAX / JSON / content negotiation
    // -------------------------------------------------------------------------

    public function testIsXmlHttpRequestReturnsTrueForAjax(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertTrue($req->isXmlHttpRequest());
        $this->assertTrue($req->ajax());
    }

    public function testIsXmlHttpRequestReturnsFalseWhenAbsent(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->assertFalse($req->ajax());
    }

    public function testIsJsonReturnsTrueForApplicationJson(): void
    {
        $req = $this->makeRequest('POST', 'https://example.com/', ['Content-Type' => 'application/json']);
        $this->assertTrue($req->isJson());
    }

    public function testIsJsonReturnsFalseForHtml(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['Content-Type' => 'text/html']);
        $this->assertFalse($req->isJson());
    }

    public function testIsHtmlReturnsTrueForTextHtml(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['Content-Type' => 'text/html; charset=utf-8']);
        $this->assertTrue($req->isHtml());
    }

    public function testWantsJsonReturnsTrueWhenAcceptContainsJson(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['Accept' => 'application/json']);
        $this->assertTrue($req->wantsJson());
        $this->assertFalse($req->exceptJson());
    }

    public function testWantsJsonReturnsFalseWhenNoAccept(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->assertFalse($req->wantsJson());
        $this->assertTrue($req->exceptJson());
    }

    public function testPjaxReturnsTrueWhenHeaderPresent(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/', ['X-PJAX' => 'true']);
        $this->assertTrue($req->pjax());
    }

    public function testPjaxReturnsFalseWhenHeaderAbsent(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->assertFalse($req->pjax());
    }

    // -------------------------------------------------------------------------
    // Pattern matching (is())
    // -------------------------------------------------------------------------

    public function testIsMatchesSimplePath(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/admin/users');
        $req->setBasePath('');
        $this->assertTrue($req->is('/admin/*'));
        $this->assertFalse($req->is('/dashboard/*'));
    }

    // -------------------------------------------------------------------------
    // Route resolver
    // -------------------------------------------------------------------------

    public function testGetRouteResolverReturnsDefaultClosureReturningNull(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $result = ($req->getRouteResolver())();
        $this->assertNull($result);
    }

    public function testSetRouteResolverStoresCallback(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $req->setRouteResolver(fn() => ['GET', '/home', []]);
        $this->assertNotNull(($req->getRouteResolver())());
    }

    public function testFingerprintThrowsWhenNoRoute(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/');
        $this->expectException(HttpException::class);
        $req->fingerprint();
    }

    public function testFingerprintReturnsSha1WithRoute(): void
    {
        $req = $this->makeRequest('GET', 'https://example.com/home', [], null, ['REMOTE_ADDR' => '127.0.0.1']);
        $req->setRouteResolver(fn() => ['GET', '/home', []]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $req->fingerprint());
    }

    // -------------------------------------------------------------------------
    // getUriFromGlobals
    // -------------------------------------------------------------------------

    public function testGetUriFromGlobalsBuildsHttpsUri(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/path?q=1';

        $uri = Request::getUriFromGlobals();

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('/path', $uri->getPath());
        $this->assertSame('q=1', $uri->getQuery());

        unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
    }
}
