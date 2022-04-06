<?php
namespace Moebius\AsyncFile;

use Generator;
use Moebius\Coroutine\Unblocker;
use function M\{suspend, readable, writable, interrupt};

class GlobStreamWrapper
{
    private static bool $registered = false;
    private $generator;

    public static function register(): void {
        if (self::$registered) {
            throw new \Exception("Already registered");
        }
        stream_wrapper_unregister('glob');
        stream_wrapper_register('glob', self::class);
        echo "registered globstreamwrapper\n";
    }

    public static function unregister(): void {
        if (!self::$registered) {
            throw new \Exception("Not registered");
        }
        stream_wrapper_unregister('glob');
        stream_wrapper_restore('glob');
    }

    public function dir_opendir(string $pattern, int $options = 0): bool
    {
die("glob opendir");
echo "opendir: $pattern\n";
        suspend();
        $pattern = substr($pattern, 7); // crop 'glob://' prefix
        $pattern = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $pattern);

        $this->generator = (function() use ($pattern) {
            foreach (glob($pattern, GLOB_NOSORT) as $result) {
                interrupt();
                yield $result;
            }
        })();

        return $this->generator->valid();
    }

    public function dir_readdir(): string
    {
die("glob readdir");
echo "readdir\n";
        $path = $this->generator->current() ?: '';
        $this->generator->next();
        return $path;
    }

    public function dir_rewinddir(): bool
    {
die("glob rewinddir");
echo "rewinddir\n";
        suspend();
        $this->generator->rewind();
        return $this->generator->valid();
    }

    public function dir_closedir(): bool
    {
echo "closedir\n";
        $this->generator = null;
        return true;
    }
}
