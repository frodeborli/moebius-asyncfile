<?php
namespace Moebius\AsyncFile;

use Moebius\Coroutine as Co;
use Moebius\Coroutine\Unblocker;

class FileStreamWrapper extends Unblocker {

    const PROTOCOL = 'file';

    protected static bool $registered = false;
    protected static ?int $lastErrorCode = null;
    protected static ?string $lastErrorMessage = null;

    public static function register(): void {
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

    protected static function wrap(bool $unregister, callable $callback, mixed ...$args): mixed {
        if ($unregister) {
            stream_wrapper_unregister(static::PROTOCOL);
            stream_wrapper_restore(static::PROTOCOL);
        }
        self::$lastErrorCode = null;
        self::$lastErrorMessage = null;
        set_error_handler(function(int $code, string $message, string $file, int $line) use (&$errorCode, &$errorMessage) {
            self::$lastErrorCode = $errorCode;
            self::$lastErrorMessage = $errorMessage;
        });
        $t = hrtime(true);
        $result = $callback(...$args);
        $t = hrtime(true) - $t;
        if ($t > 10000000) { // 1/100th of a second is considered blocking
            // This call appears to be blocking
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            array_shift($bt);
            error_log("Moebius/FileStreamWrapper::".$bt[0]['function']." took ".($t/1000000)." ms and appears to block", 0);
        }
        restore_error_handler();
        if ($unregister) {
            stream_wrapper_unregister(static::PROTOCOL);
            stream_wrapper_register(static::PROTOCOL, self::class);
        }
        return $result;
    }

    protected $dirHandle = null;

    public function dir_closedir(): bool {
//        $this->suspend();
        self::wrap(false, closedir(...), $this->dirHandle);
        return true;
    }

    public function dir_opendir(string $path, int $options=0): bool {
//        $this->suspend();
        return !!($this->dirHandle = @self::wrap(true, opendir(...), $path, $this->context));
    }

    public function dir_readdir(): string|false {
        return self::wrap(false, readdir(...), $this->dirHandle);
    }

    public function dir_rewinddir(): bool {
//        $this->suspend();
        return self::wrap(false, rewinddir(...), $this->dirHandle);
    }

    public function mkdir(string $path, $mode, int $options=0): bool {
//        $this->suspend();
        return self::wrap(true, mkdir(...), $path, $mode, (bool) ($options & STREAM_MKDIR_RECURSIVE));
    }

    public function rename(string $pathFrom, $pathTo): bool {
//        $this->suspend();
        return self::wrap(true, rename(...), $pathFrom, $pathTo);
    }

    public function rmdir(string $path): bool {
//        $this->suspend();
        return self::wrap(true, rmdir(...), $path);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool {
        /**
         * Modify the mode so that we can open this file in a non-blocking manner
         */
        $isNonBlocking = strpos($mode, 'n') !== false;
        $fp = self::wrap(true, fopen(...), $path, $mode . ($isNonBlocking ? '' : 'n'), (bool) ($options & STREAM_USE_PATH), $this->context);
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
        $this->path = $path;

        $this->pretendNonBlocking = $isNonBlocking;

        // We added 'n' to the mode string, but we'll call stream_set_blocking() as well
        stream_set_blocking($this->fp, false);
//        $this->suspend();
        return true;
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool {
//        $this->suspend();
        switch ($option) {
            case STREAM_META_TOUCH:
                $result = self::wrap(touch(...), $path, $value[0], $value[1]);
                break;
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                $result = self::wrap(chown(...), $path, $value);
                break;
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                $result = self::wrap(chgrp(...), $path, $value);
                break;
            case STREAM_META_ACCESS:
                $result = self::wrap(chmod(...), $path, $value);
                break;
        }
        throw new \Exception("stream_metadata not implemented");
    }

    public function unlink(string $path): bool {
//        $this->suspend();
        return self::wrap(true, unlink(...), $path);
    }

    public function url_stat(string $path, $flags): array|false {
//        $this->suspend();
        if ($flags & STREAM_URL_STAT_LINK) {
            return self::wrap(true, lstat(...), $path);
        } else {
            return self::wrap(true, stat(...), $path);
        }
    }
}
