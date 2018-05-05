#!/bin/bash
screen -d -m -S mumble-sso-core-auth bash -c 'cd authenticator && ./run.sh'
screen -d -m -S mumble-sso-core-runner bash -c 'cd refresher && ./run.sh'
