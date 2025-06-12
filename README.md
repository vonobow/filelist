# tiny-filelist

CAUTION: This is mainly intended to use in closed environment, such as containers or ssh port forwarding. **DO NOT USE ON SHARED SERVERS,** it leaks your files to other users.

* Simple file upload/download utility written in PHP.
* It can list, upload, and download files.
* It CANNOT remove any files for safety.
* Creating directories can be done by uploading files with containing directories.

 If you are interested in showing markdown, visit `with-md` branch.

## Requirements

* PHP (>= 8.0)
* php-intl

### Limitation

* A path is absolute or not is decided by whether the path begins with a slash or not, so tiny-filelist might not work on Windows, whose absolute paths start with `X:\\`

## Invocation

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

#### Invocation

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
* Files shown in italic font are special files.

### Symbolic links

* Synbolic links are shonw with `->`.
* The path just after `->` is the direct target of the symbolic link.
* The path after the direct target is the final target (`realpath`ed) target.
* The hyperlinks point the container (the parent directory) of each target.

### Thumbnails

* For a file named `filename`, if there is a file named `th/filename`, this file is used as the thumbnail.
* Thumbnail files can be generated, for instance, by using ImageMagick like following.

```
magick filemame.jpg -auto-orient -resize "x100>" th/filename.jpg
```

## LICENSE

AGPLv3, see [COPYING](COPYING).

Copyright 2025 akamoz.jp
