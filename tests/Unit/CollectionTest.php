<?php

declare(strict_types=1);

namespace Horizom\Http\Tests\Unit;

use Horizom\Http\Collection\Collection;
use Horizom\Http\Exceptions\BadRequestException;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    private function make(array $data = []): Collection
    {
        return new Collection($data);
    }

    // -------------------------------------------------------------------------
    // all()
    // -------------------------------------------------------------------------

    public function testAllReturnsAllParameters(): void
    {
        $col = $this->make(['foo' => 'bar', 'baz' => 'qux']);
        $this->assertSame(['foo' => 'bar', 'baz' => 'qux'], $col->all());
    }

    public function testAllWithKeyReturnsArrayValue(): void
    {
        $col = $this->make(['tags' => ['php', 'http']]);
        $this->assertSame(['php', 'http'], $col->all('tags'));
    }

    public function testAllWithKeyThrowsWhenValueIsNotArray(): void
    {
        $col = $this->make(['name' => 'Alice']);
        $this->expectException(BadRequestException::class);
        $col->all('name');
    }

    // -------------------------------------------------------------------------
    // keys()
    // -------------------------------------------------------------------------

    public function testKeysReturnsParameterKeys(): void
    {
        $col = $this->make(['a' => 1, 'b' => 2]);
        $this->assertSame(['a', 'b'], $col->keys());
    }

    // -------------------------------------------------------------------------
    // get / set / has / remove
    // -------------------------------------------------------------------------

    public function testGetReturnsValueByKey(): void
    {
        $col = $this->make(['x' => 42]);
        $this->assertSame(42, $col->get('x'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $col = $this->make([]);
        $this->assertSame('default', $col->get('missing', 'default'));
        $this->assertNull($col->get('missing'));
    }

    public function testSetStoresValue(): void
    {
        $col = $this->make([]);
        $col->set('key', 'value');
        $this->assertSame('value', $col->get('key'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $col = $this->make(['a' => 1]);
        $this->assertTrue($col->has('a'));
        $this->assertFalse($col->has('b'));
    }

    public function testHasReturnsTrueForNullValue(): void
    {
        $col = $this->make(['nullKey' => null]);
        $this->assertTrue($col->has('nullKey'));
    }

    public function testRemoveDeletesKey(): void
    {
        $col = $this->make(['a' => 1, 'b' => 2]);
        $col->remove('a');
        $this->assertFalse($col->has('a'));
        $this->assertTrue($col->has('b'));
    }

    // -------------------------------------------------------------------------
    // replace / add
    // -------------------------------------------------------------------------

    public function testReplaceOverwritesAllParameters(): void
    {
        $col = $this->make(['a' => 1]);
        $col->replace(['b' => 2]);
        $this->assertSame(['b' => 2], $col->all());
    }

    public function testAddMergesParameters(): void
    {
        $col = $this->make(['a' => 1]);
        $col->add(['b' => 2, 'a' => 99]);
        $this->assertSame(99, $col->get('a'));
        $this->assertSame(2, $col->get('b'));
    }

    // -------------------------------------------------------------------------
    // Type-coercion helpers
    // -------------------------------------------------------------------------

    public function testGetIntConvertsToInteger(): void
    {
        $col = $this->make(['count' => '42']);
        $this->assertSame(42, $col->getInt('count'));
    }

    public function testGetIntReturnsDefaultWhenMissing(): void
    {
        $col = $this->make([]);
        $this->assertSame(0, $col->getInt('missing'));
        $this->assertSame(5, $col->getInt('missing', 5));
    }

    public function testGetBooleanReturnsTrueForTruthyValues(): void
    {
        $col = $this->make(['flag' => '1']);
        $this->assertTrue($col->getBoolean('flag'));
    }

    public function testGetBooleanReturnsFalseForFalsyValues(): void
    {
        $col = $this->make(['flag' => '0']);
        $this->assertFalse($col->getBoolean('flag'));
    }

    public function testGetAlphaStripsNonAlphaChars(): void
    {
        $col = $this->make(['val' => 'abc123!@#']);
        $this->assertSame('abc', $col->getAlpha('val'));
    }

    public function testGetAlnumStripsNonAlnumChars(): void
    {
        $col = $this->make(['val' => 'abc123!@#']);
        $this->assertSame('abc123', $col->getAlnum('val'));
    }

    public function testGetDigitsStripsNonDigits(): void
    {
        $col = $this->make(['val' => 'abc123']);
        $this->assertSame('123', $col->getDigits('val'));
    }

    // -------------------------------------------------------------------------
    // Countable / IteratorAggregate
    // -------------------------------------------------------------------------

    public function testCountReturnsNumberOfParameters(): void
    {
        $col = $this->make(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertCount(3, $col);
    }

    public function testCollectionIsIterable(): void
    {
        $col = $this->make(['x' => 10, 'y' => 20]);
        $keys = [];
        foreach ($col as $key => $value) {
            $keys[] = $key;
        }
        $this->assertSame(['x', 'y'], $keys);
    }

    // -------------------------------------------------------------------------
    // filter()
    // -------------------------------------------------------------------------

    public function testFilterValidatesEmail(): void
    {
        $col = $this->make(['email' => 'user@example.com']);
        $this->assertSame('user@example.com', $col->filter('email', null, FILTER_VALIDATE_EMAIL));
    }

    public function testFilterRejectsInvalidEmail(): void
    {
        $col = $this->make(['email' => 'not-an-email']);
        $this->assertFalse($col->filter('email', null, FILTER_VALIDATE_EMAIL));
    }
}
