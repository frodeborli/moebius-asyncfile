<?php
require(__DIR__.'/../vendor/autoload.php');

use function M\{go, await};

$name = tempnam(sys_get_temp_dir(), 'moebius-asyncfile-test');
$fifo = $name.'.fifo';
posix_mkfifo($fifo, 0600);

register_shutdown_function(function() use ($name, $fifo) {
    unlink($name);
    unlink($fifo);
});

go(function() {
    echo "This should happen\n";
});

go(function() {
    sleep(0.5);
    die("The fifo file was opened in blocking mode, so we must terminate\n");
});


$fp = fopen($fifo, 'r');
echo "This should not happen\n";

