<?php

declare(strict_types=1);

const STATIC_CACHE_ITERATIONS = 1_000_000;
const STATIC_CACHE_WARMUPS = 2;
const STATIC_CACHE_ROUNDS = 5;

class StaticCacheData
{
    public static array $cache = [];

    public static function getData(): array
    {
        $class = static::class;
        if (!isset(self::$cache[$class])) {
            self::$cache[$class] = ['table' => 'users'];
        }
        return self::$cache[$class];
    }

    public static function getTable(): string
    {
        return static::getData()['table'];
    }
}

function measureStaticCacheGetData(): array
{
    $best = PHP_FLOAT_MAX;
    $checksum = 0;
    for ($round = 0; $round < STATIC_CACHE_WARMUPS + STATIC_CACHE_ROUNDS; $round++) {
        $start = hrtime(true);
        for ($i = 0; $i < STATIC_CACHE_ITERATIONS; $i++) {
            StaticCacheData::getData();
        }
        $elapsed = hrtime(true) - $start;
        if ($round >= STATIC_CACHE_WARMUPS && $elapsed < $best) {
            $best = $elapsed;
        }
        $checksum += count(StaticCacheData::getData());
    }
    return [$best / STATIC_CACHE_ITERATIONS, $checksum];
}

function measureStaticCacheGetTable(): array
{
    $best = PHP_FLOAT_MAX;
    $checksum = 0;
    for ($round = 0; $round < STATIC_CACHE_WARMUPS + STATIC_CACHE_ROUNDS; $round++) {
        $start = hrtime(true);
        for ($i = 0; $i < STATIC_CACHE_ITERATIONS; $i++) {
            StaticCacheData::getTable();
        }
        $elapsed = hrtime(true) - $start;
        if ($round >= STATIC_CACHE_WARMUPS && $elapsed < $best) {
            $best = $elapsed;
        }
        $checksum += strlen(StaticCacheData::getTable());
    }
    return [$best / STATIC_CACHE_ITERATIONS, $checksum];
}

function main(): void
{
    StaticCacheData::getData();

    [$getData, $getDataChecksum] = measureStaticCacheGetData();
    [$getTable, $getTableChecksum] = measureStaticCacheGetTable();

    echo "get_data_ns={$getData}\n";
    echo "get_table_ns={$getTable}\n";
    echo "checksum_get_data={$getDataChecksum}\n";
    echo "checksum_get_table={$getTableChecksum}\n";
}
