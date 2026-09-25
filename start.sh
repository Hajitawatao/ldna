#!/bin/sh
# Starts the LDNA app and the mock employee portal with PHP only (no Node.js needed).
cd "$(dirname "$0")"
PHP="${PHP_BIN:-php}"
"$PHP" -S 0.0.0.0:8100 -t mock-portal &
PORTAL=$!
trap 'kill $PORTAL' EXIT INT TERM
echo "  App:         http://localhost:5177/"
echo "  Mock portal: http://localhost:8100/"
"$PHP" -S 0.0.0.0:5177 server.php
