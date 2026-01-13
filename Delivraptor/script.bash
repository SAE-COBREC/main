#!/bin/bash
SIGNAL_FILE="/tmp/php_signal.flag"
echo "UPDATE"
echo "STATUS_UP 9" | nc -q 1 127.0.0.1 9000
