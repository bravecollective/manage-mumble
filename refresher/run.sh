#!/bin/bash

while true; do
    php mumble-sso-core-runner.php 2>&1 | tee -a mumble-sso-core-runner.log
    sleep 5
done
