<?php
namespace Moebius\AsyncFile;

use Fiber;
use Closure;
use Moebius\Coroutine as Co;
use Moebius\Coroutine\Unblocker;
use Moebius\Loop;

class_exists(Unblocker::class) or die("not found");
class_exists(\Moebius\Loop\Readable::class);

use const STREAM_REPORT_ERRORS;

class FileStreamWrapper {

    private static bool $registered = false;
    private static bool $ready = false;

    public static function register(): void {
        if (!self::$ready) {
            $fp = \fopen(__FILE__, 'r');
            $fp2 = Unblocker::unblock($fp);
            \stream_get_contents($fp2);
            self::$ready = true;
        }
        if (self::$registered) {
            throw new \LogicException("Already registered");
        }
        self::$registered = true;
        \stream_wrapper_unregister('file');
        \stream_wrapper_register('file', self::class);
    }

    public static function unregister(): void {
        if (!self::$registered) {
            throw new \LogicException("Not registered");
        }
        self::$registered = false;
        \stream_wrapper_unregister('file');
        \stream_wrapper_restore('file');
    }

    private static function wrap(Closure $callable) {
        self::unregister();
        try {
            return $callable();
        } catch (\Throwable $e) {
            echo get_class($e).": ".$e->getMessage()."\n".$e->getTraceAsString()."\n";
            throw $e;
        } finally {
            self::register();
        }
    }

    private bool $blocking;
    private ?float $readTimeout = null;
    private ?float $writeTimeout = null;
    private $fp; // stream
    private $dh; // dirhandle

    public function stream_cast(int $cast_as) {
        return $this->fp;
    }

    /**
     * open the stream
     *
     * @param string      $path        the path to open
     * @param string      $mode        mode for opening
     * @param int         $options     options for opening
     * @param string|null $opened_path full path that was actually opened
     */
    public function stream_open(string $path, string $mode, int $options, ?string $opened_path = null): bool
    {
        //echo "stream_open($path, $mode)\n";
        $this->fp = self::wrap(function() use ($path, $mode, $options) {
            if (strpos($mode, 'n')===false) {
                $this->blocking = true;
                $mode .= 'n';
            } else {
                $this->blocking = false;
            }
            return \fopen($path, $mode, 0 === $options & \STREAM_USE_PATH, $this->context);
        });

        return !!$this->fp;
    }

    public function stream_close(): void {
        \fclose($this->fp);
    }

    public function stream_eof(): bool {
        return \feof($this->fp);
    }

    public function stream_flush(): bool {
        $this->writable();
        return \fflush($this->fp);
    }

    public function stream_flock(int $operation): bool {
        return flock($this->fp, $operation, $would_block);
    }

    public function stream_metadata(string $path, int $option, $value): bool {
        return self::wrap(function() use ($path, $options, $value) {
            switch ($options) {
                case STREAM_META_TOUCH:
                    if (!empty($value)) {
                        $success = \touch($path, $value[0], $value[1]);
                    } else {
                        $success = \touch($path);
                    }
                    break;
                case STREAM_META_OWNER_NAME:
                    // fall through
                case STREAM_META_OWNER:
                    $success = \chown($path, $value);
                    break;
                case STREAM_META_GROUP_NAME:
                    // fall through
                case STREAM_META_GROUP:
                    $success = \chgrp($path, $value);
                    break;
                case STREAM_META_ACCESS:
                    $success = \chmod($path, $value);
                    break;
                default:
                    $success = false;
            }
            return $success;
        });
    }

    public function stream_read(int $count): string|false {
        if ($this->blocking) {
            do {
                $this->readable();
                $chunk = \fread($this->fp, $count);
            } while ($chunk === '');
            return $chunk;
        } else {
            $this->readable();
            return \fread($this->fp, $count);
        }
    }

    public function stream_seek(int $offset, int $whence=SEEK_SET): bool {
        return 0 === \fseek($this->fp, $offset, $whence);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool {
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                // The method was called in response to stream_set_blocking()
                $this->blocking = $arg1 !== 0;
                return true;

            case STREAM_OPTION_READ_TIMEOUT:
                // The method was called in response to stream_set_timeout()
                $this->readTimeout = $arg1 + ($arg2 / 1000000);
                \stream_set_timeout($this->fp, $arg1, $arg2);
                return true;

            case STREAM_OPTION_WRITE_BUFFER:
                // The method was called in response to stream_set_write_buffer()
                return 0 === \stream_set_write_buffer($this->fp, $arg2);

            case STREAM_OPTION_READ_BUFFER:
                return 0 === \stream_set_read_buffer($this->fp, $arg2);

            default :
                trigger_error("Unknown stream option $option with arguments ".json_encode($arg1)." and ".json_encode($arg2), E_USER_ERROR);
                return false;
        }
        return false;
    }

    public function stream_stat(): array|false {
        return \fstat($this->fp);
    }

    public function stream_tell(): int {
        return \ftell($this->fp);
    }

    public function stream_truncate(int $new_size): bool {
$this->writable();
        return \ftruncate($this->fp, $new_size);
    }

    public function stream_write(string $data): int {
$this->writable();
        return \fwrite($this->fp, $data);
    }

    public function dir_closedir(): bool {
        \closedir($this->dh);
    }

    public function dir_opendir(string $path, int $options=0): bool {
        return static::wrap(function() use ($path, $options) {
            $this->dh = \opendir($path, $options);
            return !!$this->dh;
        });
    }

    public function dir_readdir(): string|false {
        return \readdir($this->dh);
    }

    public function dir_rewinddir(): bool {
        \rewinddir($this->dh);
        return true;
    }

    public function mkdir(string $path, int $mode, int $options): bool {
        return static::wrap(function() use ($path, $mode, $options) {
            return \mkdir($path, $mode, 0 !== $options & \STREAM_MKDIR_RECURSIVE, $this->context);
        });
    }

    public function rename(string $path_from, string $path_to): bool {
        return static::wrap(function() use ($path_from, $path_to) {
            return \rename($path_from, $path_to, $this->context);
        });
    }

    public function rmdir(string $path, int $options): bool {
        return static::wrap(function() use ($path, $options) {
            return \rmdir($path, $this->context);
        });
    }

    public function unlink(string $path): bool {
        return static::wrap(function() use ($path) {
            return \unlink($path, $this->context);
        });
    }

    public function url_stat(string $path, int $flags): array|false {
        if ($flags & STREAM_URL_STAT_LINK) {
            return static::wrap(function() use ($path, $flags) {
                if ($flags & \STREAM_URL_STAT_QUIET) {
                    return @\lstat($path);
                } else {
                    return \lstat($path);
                }
            });
        } else {
            return static::wrap(function() use ($path, $flags) {
                if ($flags & \STREAM_URL_STAT_QUIET) {
                    return @\stat($path);
                } else {
                    return \stat($path);
                }
            });
        }
    }

    private function readable(): void {
        if (!Fiber::getCurrent()) {
            //echo "blocking readable\n";
            do {
                $reads = [ $this->fp ];
                $void = [];
                $count = \stream_select($reads, $void, $void, 1, 0);
            } while ($count !== 1);
        } else {
            //echo "async readable\n";
            Co::readable($this->fp);
        }
    }

    private function writable(): void {
        if (!Fiber::getCurrent()) {
            //echo "blocking writable\n";
            do {
                $writes = [ $this->fp ];
                $void = [];
                $count = \stream_select($void, $reads, $void, 1, 0);
            } while ($count !== 1);
        } else {
            //echo "async writable\n";
            Co::writable($this->fp);
        }
    }

    private static function suspend(): void {
        if (Fiber::getCurrent()) {
            Co::suspend();
        }
    }

}
