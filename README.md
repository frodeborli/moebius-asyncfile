moebius/async-file
==================

This components provides automatic context switching between coroutines for moebius/coroutine. This means that any
PHP component perform file system operations will automatically be asynchronously non-blocking when combined
with moebius/coroutine.

Important
---------

The library works by hooking into the file:// stream wrapper. It is possible that some semantics are different
from what you are used to, but we have tried to avoid that.

If you observe some differences, please post an issue on https://github.com/frodeborli/moebius-asyncfile
