<?php

namespace Tests;

use Sarahman\SimpleCache\FileSystemCache;

class FileSystemCacheTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     * @throws \Exception
     */
    public function itChecksDataClearingFromCache(): void
    {
        $cache = new FileSystemCache();
        $cache->clear();

        $this->assertEmpty($cache->all());
    }

    /**
     * @test
     * @dataProvider dataProviderForChecksDataExistence
     * @param string $key
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function itChecksDataExistenceInCache(string $key): void
    {
        $cache = new FileSystemCache();
        $cache->clear();

        $this->assertFalse($cache->has($key));

        $cache->set($key, 'sample data');
        $this->assertTrue($cache->has($key));

        // Set Cache key.
        $cache->delete($key);
        $this->assertFalse($cache->has($key));
    }

    /**
     * @test
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Exception
     */
    public function itChecksDataStoredInTemporaryFilesDirectory(): void
    {
        $cache = new FileSystemCache();

        $this->assertInstanceOf('Sarahman\SimpleCache\FileSystemCache', $cache);

        $cache->clear();

        $this->assertFalse($cache->has('custom_key'));

        // Set Cache key.
        $cache->set('custom_key', [
            'sample' => 'data',
            'another' => 'data'
        ]);

        $this->assertTrue($cache->has('custom_key'));

        // Get Cached key data.
        $this->assertArrayHasKey('sample', $cache->get('custom_key'));
        $this->assertArrayHasKey('another', $cache->get('custom_key'));
    }

    /**
     * @test
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Exception
     */
    public function itChecksDataStoredInTheGivenDirectory(): void
    {
        $cache = new FileSystemCache('tmp');

        $this->assertInstanceOf('Sarahman\SimpleCache\FileSystemCache', $cache);

        $cache->clear();

        $this->assertFalse($cache->has('custom_key'));

        // Set Cache key.
        $cache->set('custom_key', [
            'sample' => 'data',
            'another' => 'data'
        ]);

        $this->assertTrue($cache->has('custom_key'));

        // Get Cached key data.
        $this->assertArrayHasKey('sample', $cache->get('custom_key'));
        $this->assertArrayHasKey('another', $cache->get('custom_key'));
    }

    /**
     * @test
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Exception
     */
    public function itChecksDataRemovalWhenItsLifetimeOfCachingPassed(): void
    {
        $cache = new FileSystemCache();

        // Set Cache key.
        $cache->set('some_data', 'to be removed', 5);
        $this->assertTrue($cache->has('some_data'));

        sleep(6);
        $this->assertFalse($cache->has('some_data'));
    }

    /**
     * @test
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Exception
     */
    public function itChecksCachedDataLifetimeIncrementing(): void
    {
        $cache = new FileSystemCache();

        $cache->set('some_data', 'to be touched', 5);
        $this->assertTrue($cache->has('some_data'));

        $cache->touch('some_data', 5);
        $this->assertTrue($cache->has('some_data'));

        sleep(6);
        $this->assertFalse($cache->touch('some_data', 10));
    }

    public function dataProviderForChecksDataExistence(): array
    {
        return [
            ['custom_key'],
            ['custom_key.dot'],
            ['custom_key-hyphen'],
            ['key_digit_1231'],
        ];
    }
}
