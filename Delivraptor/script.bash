#!/bin/bash
echo "STATUS_UP 8" | nc -q 1 127.0.0.1 9000
echo "STATUS_UP 9" | nc -q 1 127.0.0.1 9000
echo -e "LOGIN Alizon mdp\nSTATUS_UP 10" | nc -q 1 127.0.0.1 9000
echo -e "LOGIN Alizon mdp\nSTATUS_UP 11" | nc -q 1 127.0.0.1 9000
