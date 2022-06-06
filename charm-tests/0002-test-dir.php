<?php
Moebius\Coroutine::go(function() {
    echo "First\n";
    Moebius\Coroutine::suspend();
    echo "Second\n";
    Moebius\Coroutine::suspend();
    echo "Third\n";
    Moebius\Coroutine::suspend();
    echo "Forth\n";
    Moebius\Coroutine::suspend();
    echo "Fifth\n";
    Moebius\Coroutine::suspend();
    echo "Sixth\n";
});
clearstatcache();

$r = opendir(__DIR__);
while (false !== ($found = readdir($r))) {
    if ($found === basename(__FILE__)) {
        echo "Found the file\n";
    }
}

$r = opendir(__DIR__);
while (false !== ($found = readdir($r))) {
    if ($found === basename(__FILE__)) {
        echo "Found the file\n";
    }
}

$r = opendir(__DIR__);
while (false !== ($found = readdir($r))) {
    if ($found === basename(__FILE__)) {
        echo "Found the file\n";
    }
}

$r = opendir(__DIR__);
while (false !== ($found = readdir($r))) {
    if ($found === basename(__FILE__)) {
        echo "Found the file\n";
    }
}
