Composer non-destructive archive installer
==========================================



What's in this thing Anyway?
----------------------------



This really only does something very simple which is to manually handle the decompression of manually defined packages so as to not disrupt nested package installs.  This is basically a straight rip of mouf/archive-installer (http://mouf-php.com), the only difference is that it's made to be slightly more flexible in terms of configuration.


Notes:


`"always-install": "true"` - This bypasses the version look up and does a full install every time useful for deployments where depenedencies are rebuilt each deploy - the default is `true`.


`"omit-first-directory": "true"` - This omits the first directory of the zip


### Usage


````json
{
    "name": "namespace/package-name",
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "drupal/drupal",
                "type": "non-destructive-archive-installer",
                "version": "7.28",
                "dist": {
                    "url": "http://ftp.drupal.org/files/projects/drupal-7.28.zip",
                    "type": "zip"
                },
                "require": {
                    "azt3k/non-destructive-archive-installer" : "*"
                },
                "extra": {
                    "always-install": "true",
                    "omit-first-directory": "true",
                    "debug": "false"
                }
            }
        }
    ],
    "require": {
        "php"                                       : ">=5.4.0",
        "composer/installers"                       : ">=1.0",
        "azt3k/non-destructive-archive-installer"   : "dev-master",
        "drupal/drupal"                             : "7.28"
    },
    "extra": {
        "installer-paths": {
            "public": [
                "drupal/drupal"
            ]
        }
    }
}

````

Or:

````json
{
    "name": "namespace/package-name",
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "drupal/drupal",
                "type": "non-destructive-archive-installer",
                "version": "7.28",
                "dist": {
                    "url": "http://ftp.drupal.org/files/projects/drupal-7.28.zip",
                    "type": "zip"
                },
                "require": {
                    "azt3k/non-destructive-archive-installer" : "*"
                }
            },
            "extra": {
                "target-dir": "public",
                "omit-first-directory": "true"
            }
        }
    ],
    "require": {
        "php"                                       : ">=5.4.0",
        "composer/installers"                       : ">=1.0",
        "azt3k/non-destructive-archive-installer"   : "dev-master",
        "drupal/drupal"                             : "7.28"
    }
}

````

A practical example of why you might want to do this is for managing drupal depenedencies, e.g.

````json
{
    "name": "namespace/package-name",
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "drupal/drupal",
                "type": "non-destructive-archive-installer",
                "version": "7.28",
                "dist": {
                    "url": "http://ftp.drupal.org/files/projects/drupal-7.28.zip",
                    "type": "zip"
                },
                "require": {
                    "azt3k/non-destructive-archive-installer" : "*"
                },
                "extra": {
                    "omit-first-directory": "true"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "drupal/drupal-ckeditor",
                "type": "drupal-module",
                "version": "7.1.14",
                "dist": {
                    "url": "http://ftp.drupal.org/files/projects/ckeditor-7.x-1.14.zip",
                    "type": "zip"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "ckeditor/ckeditor",
                "type": "drupal-module",
                "version": "4.2.2",
                "dist": {
                    "url": "http://download.cksource.com/CKEditor/CKEditor/CKEditor%204.2.2/ckeditor_4.2.2_full.zip",
                    "type": "zip"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "drupal/entity",
                "type": "drupal-module",
                "version": "7.1.5",
                "dist": {
                    "url": "http://ftp.drupal.org/files/projects/entity-7.x-1.5.zip",
                    "type": "zip"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "drupal/jquery-update",
                "type": "drupal-module",
                "version": "7.2.4",
                "dist": {
                    "url": "http://ftp.drupal.org/files/projects/jquery_update-7.x-2.4.zip",
                    "type": "zip"
                }
            }
        }
    ],
    "require": {
        "php"                                       : ">=5.4.0",
        "composer/installers"                       : ">=1.0.9",
        "azt3k/non-destructive-archive-installer"   : "dev-master",
        "symfony/yaml"                              : "dev-master",
        "drush/drush"                               : "6.2.0",
        "drupal/drupal"                             : "7.28",
        "drupal/entity"                             : "7.1.5",
        "drupal/drupal-ckeditor"                    : "7.1.14",
        "drupal/jquery-update"                      : "7.2.4",
        "ckeditor/ckeditor"                         : "4.2.2",
        "d11wtq/boris"                              : "dev-master"
    },
    "extra": {
        "installer-paths": {
            "public/sites/all/modules/{$name}": [
                "drupal/drupal-ckeditor",
                "drupal/entity",
                "drupal/jquery-update"
            ],
            "public/sites/all/libraries/{$name}" : [
                "ckeditor/ckeditor"
            ],
            "public": [
                "drupal/drupal"
            ]
        }
    },
    "scripts": {
        "post-update-cmd": [
            "rm -f public/.gitignore",
            "rm -f public/CHANGELOG.txt public/COPYRIGHT.txt public/INSTALL.mysql.txt public/INSTALL.pgsql.txt public/INSTALL.sqlite.txt public/INSTALL.txt public/LICENSE.txt public/MAINTAINERS.txt public/README.txt public/UPGRADE.txt public/download-status.txt public/web.config public/modules/README.txt"
        ],
        "post-install-cmd": [
            "rm -f public/.gitignore",
            "rm -f public/CHANGELOG.txt public/COPYRIGHT.txt public/INSTALL.mysql.txt public/INSTALL.pgsql.txt public/INSTALL.sqlite.txt public/INSTALL.txt public/LICENSE.txt public/MAINTAINERS.txt public/README.txt public/UPGRADE.txt public/download-status.txt public/web.config public/modules/README.txt"
        ]
    }
}
````


Gotchas
-------

You need to make sure the target install directory exists first or the unpack will fail.