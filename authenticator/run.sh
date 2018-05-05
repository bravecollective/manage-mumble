#!/bin/bash

while true; do
    python -u mumble-sso-core-auth.py 2>&1 | tee -a mumble-sso-core-auth.log
    sleep 5
done
