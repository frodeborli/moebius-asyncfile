<?php
// Make sure we don't enable it too early, need loop and coroutine to be ready

class_exists(Moebius\Loop\Readable::class);
class_exists(Moebius\Loop\Writable::class);
class_exists(Moebius\Loop\Timer::class);
class_exists(Moebius\Promise::class);
Moebius\Loop::await(new Moebius\Loop\Timer(0));
Moebius\Coroutine::go(function() {
    Moebius\AsyncFile\FileStreamWrapper::register();
});
