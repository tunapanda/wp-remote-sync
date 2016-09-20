# wp-remote-sync [![Build Status](https://travis-ci.org/tunapanda/wp-remote-sync.svg?branch=master)](https://travis-ci.org/tunapanda/wp-remote-sync)
This plugin synchronises content with a remote wordpress site in a similar way to a distributed version control system. As of now it works to synchronize various resource types which include; posts, attachments and H5P. 

## Setup
* Install the plugin in both local and remote wordpress instances.
* After installation you should now be able to navigate settings>Remote sync in the admin menu.
* From the remote instance set the access key (if it is not already set). 
* Side note: The access key set in <a href="http://learning.tunapanda.org">learning.tunapanda.org</a> is Tunapanda1123.
* From the local instance, set both the remote url and the access key (Must match the remote key).


## How it works
Synchronisation is user driven and all operations are handled from the local endpoint. The user can do operations similar to those git provides, i.e. push, pull, etc. 

## Work in progress
Has been known to work on occasion... Needs more testing...

## Hacking
* TDD rocks!
  Check the file `bin/install-wp-tests-local.sh.template` for instructions on how to set this up. There is also a [Travis CI](https://travis-ci.org/tunapanda/wp-remote-sync/builds/) job set up to run tests on commits to master.
* We use [git submodules](https://git-scm.com/book/en/v2/Git-Tools-Submodules). However, the submodule references live in the `submodules` directory, but they are also copied and checked in to the `ext` directory. Our code relies on the files in the `ext` directory, so this means you don't have to initialize the submodules. It also means that we shouldn't change the files in the `ext` directory, but rather change the corresponding submodule and copy it in again. 
