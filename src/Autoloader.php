<?php

declare(strict_types=1);

namespace Sabri\File26;

final class Autoloader
{
    private const PREFIX = 'Sabri\\File26\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    private static function load(string $class): void
    {
        if (! str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        if ($relative === false || $relative === '') {
            return;
        }

        $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
}
