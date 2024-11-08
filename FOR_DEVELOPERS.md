# Developer's guide

- [How to update release](#how-to-update-release-version)
- [How to update release package](#how-to-update-release-package)

### How to update release version

Update line VERSION = "4.0.2"
in Makefile file
launch make fixversions in an linux shell
This will update all xml versions (package/component/plugins)

### How to update release package

Update the README.md with new fix/features
launch make
this will create a attachments-<version>.zip in root directory
git pull of all modifications
create a release with v<version> as name
upload the package file  attachments-<version>.zip into this release 
