<?php

namespace App\Support;

use Illuminate\Contracts\Queue\ShouldQueue;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

/**
 * Discovers queued job classes and the timeout each one will actually run under.
 *
 * Used by the health check and the retry_after invariant test so a new
 * ShouldQueue job cannot ship with a timeout that exceeds its connection's
 * retry_after (Laravel would release the job to a second worker while the
 * first is still running it).
 *
 * Discovery is memoized per process: /up is polled every few seconds and must
 * not re-walk app/Jobs or re-require config/queue.php on every hit.
 */
final class QueuedJobTimeouts
{
    /** @var list<class-string>|null */
    private static ?array $jobClasses = null;

    /** @var list<string>|null */
    private static ?array $persistentConnectionNames = null;

    private static ?int $maxTimeoutSeconds = null;

    /**
     * @return list<class-string>
     */
    public static function jobClasses(): array
    {
        return self::$jobClasses ??= self::discoverJobClasses();
    }

    /**
     * @param  class-string  $class
     */
    public static function timeoutSeconds(string $class): int
    {
        $defaults = (new ReflectionClass($class))->getDefaultProperties();
        if (! array_key_exists('timeout', $defaults) || $defaults['timeout'] === null) {
            throw new RuntimeException(
                "{$class} must declare public int \$timeout so retry_after can be checked. The worker --timeout flag is not visible to this invariant."
            );
        }

        return (int) $defaults['timeout'];
    }

    /**
     * @param  class-string  $class
     */
    public static function connectionName(string $class): string
    {
        $defaults = (new ReflectionClass($class))->getDefaultProperties();

        return isset($defaults['connection']) && is_string($defaults['connection']) && $defaults['connection'] !== ''
            ? $defaults['connection']
            : (string) config('queue.default');
    }

    public static function maxTimeoutSeconds(): int
    {
        return self::$maxTimeoutSeconds ??= self::computeMaxTimeoutSeconds();
    }

    /**
     * Persistent connections shipped in config/queue.php that honour retry_after.
     *
     * Read from the config file, not the runtime config bag, so test doubles
     * added via config() are not treated as production connections.
     *
     * @return list<string>
     */
    public static function persistentConnectionNames(): array
    {
        return self::$persistentConnectionNames ??= self::discoverPersistentConnectionNames();
    }

    /**
     * @return list<class-string>
     */
    private static function discoverJobClasses(): array
    {
        $classes = [];
        $root = app_path('Jobs');
        if (! is_dir($root)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $class = 'App\\Jobs\\'.str_replace(['/', '\\'], '\\', substr($relative, 0, -4));
            if (! class_exists($class) || ! is_subclass_of($class, ShouldQueue::class)) {
                continue;
            }
            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    private static function computeMaxTimeoutSeconds(): int
    {
        $timeouts = array_map(self::timeoutSeconds(...), self::jobClasses());

        return $timeouts === [] ? 0 : max($timeouts);
    }

    /**
     * @return list<string>
     */
    private static function discoverPersistentConnectionNames(): array
    {
        $shipped = require config_path('queue.php');
        $names = [];

        foreach ($shipped['connections'] ?? [] as $name => $connection) {
            if (! is_array($connection) || ! array_key_exists('retry_after', $connection)) {
                continue;
            }
            $names[] = (string) $name;
        }

        sort($names);

        return $names;
    }
}
