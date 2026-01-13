#!/bin/bash
SIGNAL_FILE="/tmp/php_signal.flag"

echo "STATUS_UP 3" | nc -q 1 127.0.0.1 9000
echo "$(date +%s)" > "$SIGNAL_FILE"

echo "STATUS_UP 6" | nc -q 1 127.0.0.1 9000
echo "$(date +%s)" > "$SIGNAL_FILE"

echo "STATUS_UP 7" | nc -q 1 127.0.0.1 9000
echo "$(date +%s)" > "$SIGNAL_FILE"

echo "STATUS_UP 8" | nc -q 1 127.0.0.1 9000
echo "$(date +%s)" > "$SIGNAL_FILE"
echo "STATUS_UP 9" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 10" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 11" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 19" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 20" | nc -q 1 127.0.0.1 9000
