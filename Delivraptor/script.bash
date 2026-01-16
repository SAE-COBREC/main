#!/bin/bash
echo "STATUS_UP 18" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 25" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 1" | nc -q 1 127.0.0.1 9000
