<?php
require(__DIR__.'/../vendor/autoload.php');

use function M\{go, await, suspend};

go(function() {
    echo "First\n";
    suspend();
    echo "Second\n";
    suspend();
    echo "Third\n";
    suspend();
    echo "Forth\n";
    suspend();
    echo "Fifth\n";
    suspend();
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
