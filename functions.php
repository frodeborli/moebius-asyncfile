<?php
use Moebius\Coroutine;


Coroutine::events()->on(Coroutine::BOOTSTRAP_EVENT, function() {
    class_exists(Moebius\Loop::class);
    class_exists(Moebius\Loop\NativeDriver::class);

    Moebius\AsyncFile\FileStreamWrapper::register(0);

    // Futile attempt. For now, glob() calls glibc glob() and ignores any stream wrappers.
    // Moebius\AsyncFile\GlobStreamWrapper::register(0);
});
