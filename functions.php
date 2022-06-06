<?php

use Moebius\Loop;

$countdown = 4;
$checker = function() use (&$countdown) {
    if (--$countdown === 0) {
//echo "REGISTERING\n";
        Moebius\AsyncFile\FileStreamWrapper::register();
    }
//    echo "COUNTDOWN ".$countdown."\n";
};
$readableFile = \fopen(__FILE__, 'rn');
Loop::readable($readableFile, $checker);
$writableFile = \tmpfile();
Loop::writable($writableFile, $checker);
Loop::defer($checker);
Loop::delay(0.001, $checker);
Loop::run();


// Make sure we don't enable it too early, need loop and coroutine to be ready
//Moebius\AsyncFile\FileStreamWrapper::register();

//Moebius\Loop::defer(Moebius\AsyncFile\FileStreamWrapper::register(...));
