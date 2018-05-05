#!/bin/bash
kill -9 $(ps ax | grep mumble-sso-core-auth.py | grep -v grep | cut -f 1 -d \ )

