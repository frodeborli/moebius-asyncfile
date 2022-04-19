<?php
use Moebius\Coroutine;


Coroutine::events()->on(Coroutine::BOOTSTRAP_EVENT, function() {
    Moebius\AsyncFile\FileStreamWrapper::register(0);
});
