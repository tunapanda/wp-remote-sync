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

* TDD rocks!
  Install wordpress testsuite with `. ./bin/install-wp-tests-local.sh`
* It uses [git submodules](https://git-scm.com/book/en/v2/Git-Tools-Submodules). If you clone the repository, you need to do so using any of these methods:
    1. You can clone it using `git clone --recusrive`.
    2. You can clone it without `--recursive`, and after do `git submodule init` and `git submodule update` inside the
       cloned folder.
