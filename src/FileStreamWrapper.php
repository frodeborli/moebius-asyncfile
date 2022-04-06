<?php
namespace Moebius\AsyncFile;

use Moebius\Coroutine\Unblocker;
use function M\{suspend, readable, writable};

class FileStreamWrapper extends Unblocker {

    const PROTOCOL = 'file';

    protected static bool $registered = false;

    public static function register(int $flags): void {
        if (self::$registered) {
            throw new \Exception("Already registered");
        }
        stream_wrapper_unregister(static::PROTOCOL);
        stream_wrapper_register(static::PROTOCOL, self::class);
    }

    public static function unregister(): void {
        if (!self::$registered) {
            throw new \Exception("Not registered");
        }
        stream_wrapper_unregister(static::PROTOCOL);
        stream_wrapper_restore(static::PROTOCOL);
    }

    protected static function wrap(callable $callback, mixed ...$args): mixed {
        self::_interrupt();
        stream_wrapper_unregister(static::PROTOCOL);
        stream_wrapper_restore(static::PROTOCOL);
        $result = $callback(...$args);
        stream_wrapper_unregister(static::PROTOCOL);
        stream_wrapper_register(static::PROTOCOL, self::class);
        return $result;
    }

    protected $dirHandle = null;

    public function dir_closedir(): bool {
        self::wrap(closedir(...), $this->dirHandle);
        return true;
    }

    public function dir_opendir(string $path, int $options=0): bool {
        suspend();
        return !!($this->dirHandle = self::wrap(opendir(...), $path));
    }

    public function dir_readdir(): string|false {
        return self::wrap(readdir(...), $this->dirHandle);
    }

    public function dir_rewinddir(): bool {
        suspend();
        return self::wrap(rewinddir(...), $this->dirHandle);
    }

    public function mkdir(string $path, $mode, int $options=0): bool {
        suspend();
        return self::wrap(mkdir(...), $path, $mode, (bool) ($options & STREAM_MKDIR_RECURSIVE));
    }

    public function rename(string $pathFrom, $pathTo): bool {
        suspend();
        return self::wrap(rename(...), $pathFrom, $pathTo);
    }

    public function rmdir(string $path): bool {
        suspend();
        return self::wrap(rmdir(...), $path);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool {
        return self::wrap(function() use ($path, $mode, $options, &$opened_path) {
            /**
             * Modify the mode so that we can open this file in a non-blocking manner
             */
            $isNonBlocking = strpos($mode, 'n') !== false;
            $fp = fopen($path, $mode . ($isNonBlocking ? '' : 'n'), (bool) ($options & STREAM_USE_PATH));
            if (!$fp) {
                return false;
            }

            // {@see Unblocker::unblock()}
            $this->id = $id = get_resource_id($fp);
            self::$resources[$id] = $fp;
            self::$results[$id] = null; // we don't actually have the result pointer

            // {@see Unblocker::stream_open()}
            $this->fp = $fp;
            $this->mode = $mode;
            $this->options = $options;

            // not actually sure that 'n' also gives a non-blocking stream or if it only applies to the fopen call
            $this->pretendNonBlocking = $isNonBlocking;
            stream_set_blocking($this->fp, false);

            if (!$isNonBlocking) {
                readable($this->fp);
            }

            return true;
        });
    }

    public function unlink(string $path): bool {
        return self::wrap(unlink(...), $path);
    }

    public function url_stat(string $path, $flags): array|false {
        if ($flags & STREAM_URL_STAT_LINK) {
            return self::wrap(lstat(...), $path);
        } else {
            return self::wrap(stat(...), $path);
        }
    }
}
