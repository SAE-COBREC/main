#!/bin/bash

echo -e "LOGIN Alizon mdp\nSTATUS_UP 16" | nc -q 1 127.0.0.1 9000
echo -e "LOGIN Alizon mdp\nSTATUS_UP 17" | nc -q 1 127.0.0.1 9000
