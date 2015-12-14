# wp-remote-sync
Sync content with a remote wordpress site in a similar way to a distributed version control system.

## How it works

* Set this plugin up in two different wordpress instances.
* One is the "remote", this one doesn't need any settings at all.
* One is the "local", this one should be set up to point at the remote.
* From the local instance, you can do operateins similar to those git provides, i.e. push, pull, etc.

## Work in progress

Works, but could use more testing...

## Hacking

TDD rocks!

Install wordpress testsuite with `. ./bin/install-wp-tests-local.sh`