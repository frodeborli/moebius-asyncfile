<?php
// Make sure we don't enable it too early, need loop and coroutine to be ready
Moebius\Loop::await(new Moebius\Loop\Timer(0));
Moebius\Coroutine::go(function() {
    Moebius\AsyncFile\FileStreamWrapper::register();
});
