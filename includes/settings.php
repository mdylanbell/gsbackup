<?php

if (file_exists('includes/config.php'))
    require_once('includes/config.php');
    
define('DEBUG', false);

/******************************************************************************
 * Path settings
 ******************************************************************************/

define('BASE_PATH', $_SERVER['SITE_ROOT']);

define('BACKUP_PATH',   BASE_PATH . "/data/backups/");
define('DOMAINS_PATH',  BASE_PATH . "/domains/");
define('BACKUP_SCRIPT_PATH', BASE_PATH . "/users/.home/bin/gsbackup.sh");

/******************************************************************************
 * S3 settings
 ******************************************************************************/
// Python path to set, required for s3cmd
define('PYTHONPATH', BASE_PATH . "/data/python/bin:" . BASE_PATH . "/data/python/lib");
define('S3CFG_FILE_PATH', BASE_PATH . "/users/.home/.s3cfg");
define('S3CMD_FILE', BASE_PATH . "/data/python/bin/s3cmd");


/******************************************************************************
 * Architecture / Host settings
 ******************************************************************************/
// Determine site ID
$matches = array();
preg_match("~^/home/([^/]*)~", $_SERVER['SITE_ROOT'], $matches);

define('SITE_ID', $matches[1]);

$access_domain = "s" . SITE_ID . ".gridserver.com";

$ignore_domains = array(
    $access_domain
);

// Determine install path (Where are we located?)
preg_match("~^/nfs/c\d*/h\d*/mnt/(.*)~", getcwd(), $matches);
define('INSTALL_PATH', '/home/' . $matches[1] );

/******************************************************************************
 * Login settings
 ******************************************************************************/
define('HTPASSWD_FILE_PATH', INSTALL_PATH . "/.htpasswd");
define('HTACCESS_FILE_PATH', ".htaccess");

/******************************************************************************
 * Constants
 ******************************************************************************/

define('PREFIX_CONFIG_MODIFY', 'mc');
define('PREFIX_CONFIG_NEW', 'nc');
define('PREFIX_CONFIG_DELETE', 'dc');

define('PREFIX_BACKUP_FROM_CONF', 'backup');
define('PREFIX_BACKUP_ONETIME', 'otb');

define('PREFIX_EXISTING_BACKUP', 'eb');

define('ALLOWED_LABEL_CHARACTERS', '\w\-=\+#\.');  // Allowed characters (regex)

define('VERSION', "1.1");

define('TITLE', "(gs) Grid-Service Backup Tool");

?>
