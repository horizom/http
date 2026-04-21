<?php

declare(strict_types=1);

namespace Horizom\Http\Tests\Unit;

use Horizom\Http\Uri;
use PHPUnit\Framework\TestCase;

final class UriTest extends TestCase
{
    public function testConstructsFromString(): void
    {
        $uri = new Uri('https://example.com/path?foo=bar#section');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('/path', $uri->getPath());
        $this->assertSame('foo=bar', $uri->getQuery());
        $this->assertSame('section', $uri->getFragment());
    }

    public function testWithSchemeIsImmutable(): void
    {
        $uri = new Uri('http://example.com/');
        $new = $uri->withScheme('https');

        $this->assertNotSame($uri, $new);
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('https', $new->getScheme());
    }

    public function testWithHostIsImmutable(): void
    {
        $uri = new Uri('https://example.com/');
        $new = $uri->withHost('other.com');

        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('other.com', $new->getHost());
    }

    public function testWithPathIsImmutable(): void
    {
        $uri = new Uri('https://example.com/old');
        $new = $uri->withPath('/new');

        $this->assertSame('/old', $uri->getPath());
        $this->assertSame('/new', $new->getPath());
    }

    public function testWithQueryIsImmutable(): void
    {
        $uri = new Uri('https://example.com/?a=1');
        $new = $uri->withQuery('b=2');

        $this->assertSame('a=1', $uri->getQuery());
        $this->assertSame('b=2', $new->getQuery());
    }

    public function testWithPortIsImmutable(): void
    {
        $uri = new Uri('https://example.com/');
        $new = $uri->withPort(8080);

        $this->assertNull($uri->getPort());
        $this->assertSame(8080, $new->getPort());
    }

    public function testToStringReconstructsUri(): void
    {
        $uriString = 'https://example.com/path?q=1#anchor';
        $uri = new Uri($uriString);

        $this->assertSame($uriString, (string) $uri);
    }

    public function testUriWithUserInfo(): void
    {
        $uri = new Uri('https://user:pass@example.com/');

        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('user:pass@example.com', $uri->getAuthority());
    }

    public function testWithUserInfoIsImmutable(): void
    {
        $uri = new Uri('https://example.com/');
        $new = $uri->withUserInfo('admin', 'secret');

        $this->assertSame('', $uri->getUserInfo());
        $this->assertSame('admin:secret', $new->getUserInfo());
    }
}
