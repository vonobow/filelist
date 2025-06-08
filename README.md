# tiny-filelist

CAUTION: This is mainly intended to use in closed environment, such as containers or ssh port forwarding. **DO NOT USE ON SHARED SERVERS,** it leaks your files to other users.

* Simple file upload/download utility written in PHP.
* It can list, upload, and download files.
* It CANNOT remove any files for safety.
* Creating directories can be done by uploading files with containing directories.

## Requirements

* PHP (>= 8.0)
* php-intl

## Running the server

### Simplest

```
php -S localhost:12345
```

* **NO AUTHENTICATION.**
* **`localhost:12345` CAN BE ACCESSED BY ANYONE** who are on the server.
* The size of uploading file is limited by the PHP settings.
* if you can see `index.php` as `filelist/index.php` on the current directory, you can see  the file list by accessing `http://localhost:12345/filelist/index.php`.
* Creating symbolic link makes it easy to use.

### Simple

```
export FILELIST_TEMPDIR=$TMPDIR
export PHP_CLI_SERVER_WORKERS=5 # optional
php -S localhost:12345
```

* **NO AUTHENTICATION.**
* **`localhost:12345` CAN BE ACCESSED BY ANYONE** who are on the server.
* Using simultaneous, segmented upload.
* Can upload large files with PHP default settings.
* Maximum file size is limited by `$max_upload_position` in `upload-util.inc` for the security reason.

### With tiny authentication

#### Preparation

```
php path/to/mkpasshash.php
```

* This creates `.tiny-filelist-passhash` for the authentication.

#### Execution

```
export FILELIST_TEMPDIR=$TMPDIR # optional
export PHP_CLI_SERVER_WORKERS=5 # optional
php -S localhost:12345 path/to/router.php
```

* You need logging in to see the file list.

## Using from the browser

* You can show files by clicking the links. Clicked file is handled with default behavior of your browser.
* Or, explicitly download / show the file by using the triple-dot menu.
* You can upload file by drag-and-drop operation, or using "select files to upload" button.
* It may be used from mobile devices, if you can resolve https issue, which is beyond the scope of this document.
