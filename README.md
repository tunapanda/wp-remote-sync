# wp-remote-sync
This plugin synchronises content with a remote wordpress site in a similar way to a distributed version control system.As of now it works to synchronize various resource types which include; posts, attachments and H5P. 

## Setup
* Install the plugin in both local and remote wordpress instances.
* After installation you should now be able to navigate settings>Remote sync in the admin menu.
* From the remote instance set the access key (if it is not already set). 
* Side note: The access key set in <a href="http://learning.tunapanda.org">learning.tunapanda.org</a> is Tunapanda1123.
* From the local instance, set both the remote url and the access key (Must match the remote key).


## How it works
Synchronisation is user driven and all operations are handled from the local endpoint. The user can do operations similar to those git provides, i.e. push, pull, etc. For any given operation, synchronisation happens for all resources. 

## Work in progress
Works, but could use more testing...

## Hacking
* TDD rocks!
  Install wordpress testsuite with `. ./bin/install-wp-tests-local.sh`
* It uses [git submodules](https://git-scm.com/book/en/v2/Git-Tools-Submodules). If you clone the repository, you need to do so using any of these methods:
    1. You can clone it using `git clone --recusrive`.
    2. You can clone it without `--recursive`, and after do `git submodule init` and `git submodule update` inside the
       cloned folder.
