# horizom/http

[![Total Downloads](https://poser.pugx.org/horizom/http/d/total.svg)](https://packagist.org/packages/horizom/http)
[![Latest Stable Version](https://poser.pugx.org/horizom/http/v/stable.svg)](https://packagist.org/packages/horizom/http)
[![License](https://poser.pugx.org/horizom/http/license.svg)](https://packagist.org/packages/horizom/http)

A PSR-7 HTTP abstraction layer for PHP 8.0+, built on top of [Nyholm/PSR7](https://github.com/Nyholm/psr7) and [Symfony HttpFoundation](https://symfony.com/doc/current/components/http_foundation.html). Provides a rich, expressive API for working with HTTP requests, responses, URIs, streams, and file uploads.

---

## Requirements

- PHP 8.0 or higher
- Composer

## Installation

```bash
composer require horizom/http
```

---

## Features

- **Request** — PSR-7 server request with helpers for IP, headers, bearer tokens, content negotiation, URL building, and more.
- **Response** — Fluent PSR-7 response with JSON, redirect, file, and download helpers.
- **Uri** — Immutable URI value object.
- **Stream** — PSR-7 stream wrapper.
- **UploadedFile** — Symfony-backed uploaded file with extension guessing and hashed names.
- **Collections** — Type-safe parameter bags for query, body, server, and file data.
- **Exceptions** — Domain-specific HTTP and file exceptions.

---

## Quick Start

### Request

```php
use Horizom\Http\Request;

$request = Request::capture(); // Builds from PHP superglobals

// Basic accessors
$request->method();            // 'GET'
$request->path();              // '/users/42'
$request->url();               // 'https://example.com/users/42'
$request->fullUrl();           // 'https://example.com/users/42?page=1'
$request->ip();                // '203.0.113.5'
$request->userAgent();         // 'Mozilla/5.0 ...'
$request->bearerToken();       // 'eyJ...' or null

// Security
$request->isSecure();          // true for HTTPS
$request->isMethod('POST');    // true / false (case-insensitive)

// Content negotiation
$request->isJson();            // Content-Type: application/json
$request->wantsJson();         // Accept: application/json
$request->ajax();              // X-Requested-With: XMLHttpRequest
$request->pjax();              // X-PJAX header present

// URL helpers
$request->setBasePath('/app');
$request->basePath();          // '/app'
$request->baseUrl();           // 'https://example.com/app'
$request->root();              // 'https://example.com'
$request->is('/admin/*');      // wildcard path matching
```

### Request Input

```php
// Read input (query string, JSON body, form data)
$request->input('name');                    // single value
$request->input('name', 'default');        // with default
$request->all();                            // all input as array
$request->collect();                        // as Collection

// Typed accessors
$request->string('title');                 // string
$request->integer('page');                 // int
$request->float('price');                  // float
$request->boolean('active');              // bool

// Presence checks
$request->has('email');                    // true if key exists and non-null
$request->missing('token');               // true if key absent

// Filtering
$request->only('name', 'email');           // Collection with only these keys
$request->except('password');              // Collection without these keys

// Query string
$request->query('sort', 'name');

// Cookies and server params
$request->cookie('session_id');
$request->server('SERVER_NAME');

// File uploads
$request->hasFile('avatar');
$request->files('avatar');                 // UploadedFile or null
```

### Response

```php
use Horizom\Http\Response;

// JSON response
$response = (new Response())->json(['user' => 'Alice'], 200);

// Redirect
$response = (new Response())->redirect('/dashboard');
$response = (new Response())->redirect('/login', 301);

// File delivery
$response = (new Response())->file('/var/www/storage/report.pdf');

// File download
$response = (new Response())->download('/var/www/storage/report.pdf', 'report.pdf');

// Using the helper function
$response = response()->json(['status' => 'ok']);
$response = redirect('/home');
```

#### PSR-7 Immutability

`Response` is immutable — all mutating methods return a **new** instance:

```php
$original = new Response(200);
$updated  = $original->json(['ok' => true]); // $original is unchanged
```

### Uri

```php
use Horizom\Http\Uri;

$uri = new Uri('https://example.com/path?q=hello#anchor');

$uri->getScheme();    // 'https'
$uri->getHost();      // 'example.com'
$uri->getPath();      // '/path'
$uri->getQuery();     // 'q=hello'
$uri->getFragment();  // 'anchor'

// Immutable modification
$newUri = $uri->withHost('other.com')->withPath('/new');
```

### Stream

```php
use Horizom\Http\Stream;

$stream = Stream::create('Hello, world!');
$stream->read(5);      // 'Hello'
$stream->getContents(); // ', world!'
```

### UploadedFile

```php
/** @var \Horizom\Http\UploadedFile $file */
$file = $request->files('avatar');

$file->get();                    // raw file contents (string)
$file->extension();              // guessed extension from MIME type
$file->clientExtension();        // guessed from the client-provided MIME
$file->hashName();               // '3d2f...a1b2.jpg'
$file->hashName('/uploads');     // '/uploads/3d2f...a1b2.jpg'
$file->path();                   // absolute path to the temp file

// Move to final location (Symfony UploadedFile API)
$file->move('/var/www/storage', $file->hashName());
```

### Collections

#### `Collection` (generic parameter bag)

```php
use Horizom\Http\Collection\Collection;

$col = new Collection(['name' => 'Alice', 'age' => '30', 'tags' => ['php', 'http']]);

$col->get('name');              // 'Alice'
$col->get('missing', 'n/a');   // 'n/a'
$col->getInt('age');            // 30
$col->getBoolean('active');     // false (missing → false)
$col->getAlpha('slug');         // strips non-alpha characters
$col->getAlnum('code');         // strips non-alphanumeric characters
$col->getDigits('ref');         // strips non-digit characters
$col->all();                    // ['name' => 'Alice', 'age' => '30', ...]
$col->all('tags');              // ['php', 'http']
$col->keys();                   // ['name', 'age', 'tags']
$col->has('name');              // true
$col->remove('name');
$col->set('email', 'a@b.com');
$col->filter('email', null, FILTER_VALIDATE_EMAIL);
```

#### `ServerCollection`

Parses `$_SERVER` superglobal and exposes HTTP headers:

```php
use Horizom\Http\Collection\ServerCollection;

$server  = new ServerCollection($_SERVER);
$headers = $server->getHeaders(); // ['HOST' => 'example.com', 'ACCEPT' => '...', ...]
```

#### `FileCollection`

Normalises PHP's inconsistent `$_FILES` format:

```php
use Horizom\Http\Collection\FileCollection;

$files = new FileCollection($_FILES);
$file  = $files->get('avatar'); // UploadedFile | null
```

---

## Exceptions

| Exception               | When thrown                            |
| ----------------------- | -------------------------------------- |
| `HttpException`         | General HTTP error (wraps status code) |
| `BadRequestException`   | Malformed or unexpected request data   |
| `FileNotFoundException` | File does not exist or cannot be read  |
| `FileExistsException`   | Target file already exists             |

---

## API Reference

### `Request`

| Method                      | Returns              | Description                                  |
| --------------------------- | -------------------- | -------------------------------------------- |
| `create(string $uri, ...)`  | `static`             | Static factory                               |
| `capture()`                 | `static`             | Build from PHP superglobals                  |
| `method()`                  | `string`             | HTTP verb                                    |
| `isMethod(string $method)`  | `bool`               | Case-insensitive method check                |
| `path()`                    | `string`             | URI path                                     |
| `url()`                     | `string`             | URL without query string                     |
| `fullUrl()`                 | `string`             | Full URL with query string                   |
| `root()`                    | `string`             | Scheme + host                                |
| `baseUrl()`                 | `string`             | root + basePath                              |
| `basePath()`                | `string`             | Configured base path                         |
| `setBasePath(string $path)` | `void`               | Set base path                                |
| `ip()`                      | `?string`            | Client IP (CF, X-Forwarded-For, REMOTE_ADDR) |
| `userAgent()`               | `?string`            | User-Agent header                            |
| `bearerToken()`             | `?string`            | Bearer token from Authorization header       |
| `header(string $key, ...)`  | `?string`            | Single header value                          |
| `headers()`                 | `array`              | All headers                                  |
| `isSecure()`                | `bool`               | HTTPS check                                  |
| `isJson()`                  | `bool`               | Content-Type is JSON                         |
| `isHtml()`                  | `bool`               | Content-Type is HTML                         |
| `wantsJson()`               | `bool`               | Accept prefers JSON                          |
| `ajax()`                    | `bool`               | XMLHttpRequest                               |
| `pjax()`                    | `bool`               | PJAX request                                 |
| `is(string ...$patterns)`   | `bool`               | Path wildcard match                          |
| `fingerprint()`             | `string`             | SHA1 request fingerprint                     |
| `input(string $key, ...)`   | `mixed`              | Input from any source                        |
| `all()`                     | `array`              | All input                                    |
| `collect()`                 | `Collection`         | Input as collection                          |
| `has(string $key)`          | `bool`               | Key present and non-null                     |
| `missing(string $key)`      | `bool`               | Key absent                                   |
| `only(string ...$keys)`     | `Collection`         | Subset of input                              |
| `except(string ...$keys)`   | `Collection`         | Input minus specified keys                   |
| `hasFile(string $key)`      | `bool`               | Uploaded file present                        |
| `files(string $key)`        | `UploadedFile\|null` | Uploaded file                                |

### `Response`

| Method                                          | Returns  | Description                  |
| ----------------------------------------------- | -------- | ---------------------------- |
| `create(int $status, ...)`                      | `static` | Static factory               |
| `fromInstance(ResponseInterface $r)`            | `static` | Copy from existing response  |
| `json(mixed $data, ?int $status, ...)`          | `static` | JSON response                |
| `redirect(string $url, ?int $status)`           | `static` | Redirect response            |
| `redirectWithBaseUrl(?string $url, ...)`        | `static` | Redirect prepending base URL |
| `setBaseUrl(string $url)`                       | `static` | Set base URL for redirects   |
| `file(string\|resource\|StreamInterface $file)` | `static` | Serve file body              |
| `download(string $path, ?string $name)`         | `static` | Force download               |
| `view(string $path, array $data)`               | `static` | Render PHP view template     |

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

---

## License

MIT — see [LICENSE.md](LICENSE.md).
