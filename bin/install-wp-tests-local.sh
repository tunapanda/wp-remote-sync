#!/usr/bin/env bash

export WP_TESTS_DIR=`pwd`/tests/wordpress-tests-lib/
export WP_CORE_DIR=`pwd`tests/wordpres/
./bin/install-wp-tests.sh wptest root ''
