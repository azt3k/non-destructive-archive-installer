Composer non-destructive archive installer
==========================================



What's in this thing Anyway?
----------------------------



This really only does something very simple which is to manually handle the decompression of manually defined packages so as to not disrupt nested package installs.  This is basically a straight rip of mouf/archive-installer [http://mouf-php.com]



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
                "version": "7.24",
                "dist": {
                    "url": "http://ftp.drupal.org/files/projects/drupal-7.24.zip",
                    "type": "zip"
                }
            }
        }
    ],
    "require": {
        "php"                                       : ">=5.4.0",
        "composer/installers"                       : ">=1.0",
        "azt3k/non-destructive-archive-installer"   : "dev-master,
        "drupal/drupal"                             : "7.24"
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

Or maybe:

````json
{
    "name": "namespace/package-name", 
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "drupal/drupal",
                "type": "non-destructive-archive-installer",                   
                "version": "7.24",
                "dist": {
                    "url": "http://ftp.drupal.org/files/projects/drupal-7.24.zip",
                    "type": "zip"
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
        "azt3k/non-destructive-archive-installer"   : "dev-master,
        "drupal/drupal"                             : "7.24"
    }   
}

````