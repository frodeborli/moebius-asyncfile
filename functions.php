<?php
// Make sure we don't enable it too early, need loop and coroutine to be ready

Moebius\Coroutine::go(function() {
    Moebius\AsyncFile\FileStreamWrapper::register();
});
