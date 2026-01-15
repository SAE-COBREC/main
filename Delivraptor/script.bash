#!/bin/bash
echo "STATUS_UP 18" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 2" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 3" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 4" | nc -q 1 127.0.0.1 9000
