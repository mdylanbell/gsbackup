<?php

function t_head()
{
    if (DEBUG)
    {
        $style = "style.css";
        $js = "gsbackup.js";
    } else {
        $style = "style.min.css";
        $js = "gsbackup.min.js";
    }

    return <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <title>\$title</title>
    <link rel="stylesheet" href="media/css/reset.css" type="text/css" />
    <link rel="stylesheet" href="media/css/$style" type="text/css" />
    <link rel="stylesheet" href="media/css/uniform.css" type="text/css" />
    <script type="text/javascript" src="media/js/jquery-1.6.2.min.js"></script>
    <script type="text/javascript" src="media/js/jquery.uniform.min.js"></script>
    <script type="text/javascript" src="media/js/$js"></script>

    <script type="text/javascript">
        <!--
        $(function(){
            $("select, input:checkbox, input:button, input:text, input:password, input:submit").uniform();
        });
        // -->
    </script>
</head>
<body>
EOT;
}


function t_foot()
{
    return <<<EOT
</body>
</html>
EOT;
}

function t_popup()
{
    return <<<EOT
<div id="popup">
    <div id="popup-text"></div>
    <div id="popup-buttons">
        <input type="button" id="popup-button-submit" value="Confirm" />
        <input type="button" id="popup-button-cancel" class="popup-cancel" value="Cancel" />
        <input type="button" id="popup-button-close" class="popup-close" value="OK" />
<!-- MDB: New buttons
        <a rel="submit" class="btn" href="#" id="popup-button-submit" value="Confirm" />
        <a rel="cacnel" class="btn" id="popup-button-cancel"  value="Cancel" />
        <a rel="ok" class="btn" id="popup-button-close" value="OK" />
-->
    </div>
</div>
<div id="popup-background"></div>
EOT;
}

/******************************************************************************
 Select List
 ******************************************************************************/

function t_selectlist_header($v)
{
    $checked_text = "";

    if ($v['checked']) {
        $checked_text = ' checked="checked"';
    }

    $return = <<<EOT

<ol class="select-list">
<li>
    <input type="checkbox" name="{\$type}[]" value="all" id="\$prefix-all-\$type"$checked_text />
    <label for="\$prefix-all-\$type">\$label</label>
</li>
EOT;

    return $return;
}


function t_selectlist_item($v)
{
    $checked_text = "";

    if ($v['checked']) {
        $checked_text = ' checked="checked"';
    }

    $return = <<<EOT

<li>
    <input type="checkbox" name="{\$type}[]" value="\$item" id="\$prefix-\$item"$checked_text />
    <label for="\$prefix-\$item">\$item</label>
</li>
EOT;

    return $return;
}

function t_selectlist_foot()
{
    return <<<EOT

    </ol>
EOT;
}


/******************************************************************************
 Settings
 ******************************************************************************/
 
function t_settings_head()
{
    return <<<EOT

<form name="settings" method="post" class="confirm" action="update.php">
    <input type="hidden" name="type" value="\$type" />
EOT;
}


function t_settings_foot()
{
    return <<<EOT

    <input type="submit" id="settings-save" value="Save Settings" />
</form>
EOT;
}

function t_settings_database($v)
{
    return <<<EOT

<ol>
    <li>
        <label for="settings-dbhost" class="text-label">Database Host</label>
        <input type="text" name="dbhost" value="\$db_hostname" id="settings-dbhost" size="40" />
    </li><li>
        <label for="settings-database" class="text-label">Database</label>
        <input type="text" name="database" value="\$db_database" id="settings-database" size="40" />
    </li><li>
        <label for="settings-dbuser" class="text-label">Database User</label>
        <input type="text" name="dbuser" value="\$db_username" id="settings-dbuser" size="14" />
    </li><li>
        <label for="settings-dbpass" class="text-label">Database Password</label>
        <input type="password" name="dbpass" value="" id="settings-dbpass" size="20" />
    </li><li>
        <label for="settings-confirm-dbpass" class="text-label">Confirm Database Password</label>
        <input type="password" name="dbpass2" value="" id="settings-confirm-dbpass" size="20" />
    </li>
</ol>
EOT;
}


function t_settings_s3()
{
    return <<<EOT

<ol>
    <li>
        <input type="checkbox" name="settings-s3-enabled" id="settings-s3-enabled"\$s3_enabled />
        <label for="settings-s3-enabled">Upload to S3</label>
    </li><li>
        <label for="settings-s3-remotepath" class="text-label">S3 Remote Path</label>
        <input type="text" name="settings-s3-remotepath" value="\$s3_remote_path" id="settings-s3-remotepath" size="40" />
    </li><li>
        <label for="settings-s3-accesskey" class="text-label">Access Key</label>
        <input type="text" name="settings-s3-accesskey" value="\$s3_access_key" id="settings-s3-accesskey" size="40" />
    </li><li>
        <label for="settings-s3-secretkey" class="text-label">Secret Key</label>
        <input type="password" name="settings-s3-secretkey" value="\$s3_secret_key" id="settings-s3-secretkey" size="40" />
    </li><li>
        <input type="checkbox" name="settings-s3-usehttps" value="1" id="settings-s3-usehttps" \$s3_use_https />
        <label for="settings-s3-usehttps">Use HTTPS for upload</label>
    </li>
</ol>
EOT;
}

function t_settings_login()
{
    return <<<EOT
<ol>
    <li>
        <label for="settings-login-name" class="text-label">Username</label>
        <input type="text" name="settings-login-username" value="\$login_username" id="settings-login-name" size="40" />
    </li><li>
        <label for="settings-login-password" class="text-label">Login Password</label>
        <input type="password" name="settings-login-password" value="" id="settings-login-password" size="40" />
    </li><li>
        <label for="settings-login-password2" class="text-label">Confirm Login Password</label>
        <input type="password" name="settings-login-password2" value="" id="settings-login-password2" size="40" />
    </li>
</ol>
EOT;
}


/******************************************************************************
 Configurations
 ******************************************************************************/

function t_configuration($v)
{
    $s3_configuration_default = "";
    $s3_configuration_custom  = "";
    
    if ($v["custom_s3_config"]) {
        $s3_configuration_custom = ' selected="selected"';
    } else {
        $s3_configuration_default = ' selected="selected"';
    }
    
    if ($v["s3_enabled"]) {
        $s3_enabled = 'checked="checked"';
    }

    $return = <<<EOT

<form name="\$prefix-\$name" method="post" class="confirm" action="update.php">
<input type="hidden" name="id" value="\$id" />
<input type="hidden" name="type" value="\$type" />
<ol>
    <li>
        <label for="\$prefix-name" class="text-label">Name of configuration</label>
        <input type="text" name="name" value="\$name" id="\$prefix-name" size="30" />
    </li>
EOT;

    if ($v["show_num_backups"]) {
        $return .= <<<EOT

    <li>
        <label for="\$prefix-num-backups" class="left-label">Number of backups to keep</label>
        <input type="text" name="num_backups" value="\$num_backups" id="\$prefix-num-backups" size="3" />
    </li>
EOT;
    }

    $return .= <<<EOT
    
    <li>
        \$domains
    </li><li>
        \$databases
    </li><li>
        <label for="\$prefix-s3">S3 Configuration</label><br />
        <select name="custom_s3_config" id="\$prefix-s3">
            <option value="0"$s3_configuration_default>Use default settings</option>
            <option value="1"$s3_configuration_custom>Use custom settings</option>
        </select>
        <div class="panel">
            <div class="wrapper">
                <ol>
                    <li>
                        <input type="checkbox" name="s3_enabled" value="1" id="\$prefix-s3-enabled"$s3_enabled />
                        <label for="\$prefix-s3-enabled">Upload to S3</label>
                    </li><li>
                        <label for="\$prefix-s3-path" class="text-label">S3 Remote Path</label>
                        <input type="text" name="s3_remote_path" id="\$prefix-s3-path" size="40" value="\$s3_remote_path" />
                    </li>
                </ol>
            </div>
        </div>
    </li><li>
        <input type="submit" value="Save \$name" />
    </li>
</ol>
</form>
EOT;

    return $return;
}


function t_backup_configuration($nothing)
{
    return <<<EOT

<ol class="configuration-list">
    <li>
        <label for="\$prefix-\$id">\$name</label>
        <input type="button" name="\$name" value="Backup now" class="backup-button" id="\$prefix-\$id" />
<!-- MDB: New Buttons
        <a rel="\$prefix-\$id" class="btn" name="\$name" class="backup-button" id="\$prefix-\$id">Backup now</a>
-->
    </li>
</ol>
EOT;
}


function t_delete_configurations()
{
    return <<<EOT

<form id="\$type-delete-configurations" method="post" class="confirm" action="update.php">
    <input type="hidden" name="type" value="\$type" />
    \$configurations
    <input type="submit" value="Delete Selected Configurations" />
</form>
EOT;
}


/******************************************************************************
 Existing Backups
 ******************************************************************************/

function t_existing_backup_head()
{
    return <<<EOT
    
    <form id="\$prefix-delete-backups" method="post" class="confirm" action="update.php">
        <input type="hidden" name="type" value="\$type" />
EOT;
}


function t_existing_backup_foot()
{
    return <<<EOT
    
        <input type="submit" id="\$prefix-delete" value="Delete selected backups" />
    </form>
EOT;
}


function t_existing_backup_table_head()
{
    return <<<EOT
    
        <table>
            <tr>
                <td>
                    <input type="checkbox" name="\$prefix-{\$name}[]" value="all" id="\$prefix-\$name-all" />
                </td><td>
                    <label for="\$prefix-\$name-all">Select Backups</label>
                </td><td>
                    <p>File Size</p>
                </td><td>
                    <p>Date Created</p>
                </td><td>
                    <p>S3</p>
                </td><td>
                    <p>Download</p>
                </td>
            </tr>
EOT;
}


function t_existing_backup_table_item()
{
    return <<<EOT
    
            <tr>
                <td>
                    <input type="checkbox" name="\$prefix-{\$name}[]" value="{\$b->id}" id="\$prefix-{\$b->id}" />
                </td><td>
                    <label for="\$prefix-{\$b->id}">{\$b->filename}</label>
                </td><td>
                    <p>{\$b->filesize}</p>
                </td><td>
                    <p>{\$b->date_created}</p>
                </td><td>
                    <p>\$s3status</p>
                </td><td>
                    <input type="button" class="download-button" name="{\$b->filename}" value="Download" />
                </td>
            </tr>
EOT;
}


function t_existing_backup_table_foot()
{
    return <<<EOT
    
        </table>
EOT;
}


/******************************************************************************
 Automation
 ******************************************************************************/

function t_automation()
{

}

/******************************************************************************
 Configuration file
 ******************************************************************************/

function t_config_file()
{
return <<<EOT
<?php

/*
 * You shouldn't need to modify this file.  It can be updated through the
 * web application, and is overwritten when settings are saved in this way.
 */

// Database Settings
define('DB_HOSTNAME', '\$db_hostname');
define('DB_DATABASE', '\$db_database');
define('DB_USERNAME', '\$db_username');
define('DB_PASSWORD', '\$db_password');

// S3 settings
define('S3_ENABLED',     \$s3_enabled);
define('S3_REMOTE_PATH', '\$s3_remote_path');
define('S3_ACCESS_KEY',  '\$s3_access_key');
define('S3_SECRET_KEY',  '\$s3_secret_key');
define('S3_USE_HTTPS',   \$s3_use_https);

// Login settings
define('LOGIN_USERNAME', '\$login_username');

?>

EOT;

}

function t_s3cfg_file()
{
    return <<<EOT
[default]
access_key = \$s3_access_key
acl_public = False
bucket_location = US
cloudfront_host = cloudfront.amazonaws.com
cloudfront_resource = /2008-06-30/distribution
default_mime_type = binary/octet-stream
delete_removed = False
dry_run = False
encoding = ANSI_X3.4-1968
encrypt = False
force = False
get_continue = False
gpg_command = /usr/bin/gpg
gpg_decrypt = %(gpg_command)s -d --verbose --no-use-agent --batch --yes --passphrase-fd %(passphrase_fd)s -o %(output_file)s %(input_file)s
gpg_encrypt = %(gpg_command)s -c --verbose --no-use-agent --batch --yes --passphrase-fd %(passphrase_fd)s -o %(output_file)s %(input_file)s
gpg_passphrase = 
guess_mime_type = True
host_base = s3.amazonaws.com
host_bucket = %(bucket)s.s3.amazonaws.com
human_readable_sizes = True
list_md5 = False
preserve_attrs = True
progress_meter = True
proxy_host = 
proxy_port = 0
recursive = False
recv_chunk = 4096
secret_key = \$s3_secret_key
send_chunk = 4096
simpledb_host = sdb.amazonaws.com
skip_existing = False
urlencoding_mode = normal
use_https = \$s3_use_https
verbosity = WARNING

EOT;
}


function t_htpasswd_file()
{
    return <<<EOT
\$login_username:\$login_password

EOT;
}


function t_htaccess_file()
{

    if (DEBUG)
    {
    
        return <<<EOT
AddHandler php5-script .php

Options +FollowSymLinks
RewriteEngine On

RewriteCond %{SCRIPT_FILENAME} !-d
RewriteCond %{SCRIPT_FILENAME} !-f
RewriteRule ^download/(.*)$ ./download.php?file=$1

AuthUserFile \$htpasswd_file_path
AuthGroupFile /dev/null
AuthName "Private"
AuthType Basic
Require valid-user

EOT;

    } else {
    
        return <<<EOT
AddHandler php5-script .php

Options +FollowSymLinks
RewriteEngine On

RewriteCond %{SCRIPT_FILENAME} !-d
RewriteCond %{SCRIPT_FILENAME} !-f
RewriteRule ^download/(.*)$ ./download.php?file=$1

#Enable compression
AddOutputFilterByType DEFLATE text/plain
AddOutputFilterByType DEFLATE text/html
AddOutputFilterByType DEFLATE text/xml
AddOutputFilterByType DEFLATE text/css
AddOutputFilterByType DEFLATE application/xml
AddOutputFilterByType DEFLATE application/xhtml+xml
AddOutputFilterByType DEFLATE application/rss+xml
AddOutputFilterByType DEFLATE application/javascript
AddOutputFilterByType DEFLATE application/x-javascript

#Disable ETag
Header unset ETag
FileETag None

# Expires headers
# 1 YEAR
<FilesMatch "\.(ico|pdf|flv)$">
Header set Cache-Control "max-age=29030400, public"
ExpiresDefault "access plus 1 years"
</FilesMatch>

# 2 MONTHS
<FilesMatch "\.(jpg|jpeg|png|gif|swf)$">
Header set Cache-Control "max-age=4838400, public"
ExpiresDefault "access plus 2 months"
</FilesMatch>

# 1 WEEK
<FilesMatch "\.(xml|txt|css|js)$">
Header set Cache-Control "max-age=604800, public"
ExpiresDefault "access plus 1 weeks"
</FilesMatch>

# 30 MIN
<FilesMatch "\.(html|htm|php)$">
Header set Cache-Control "max-age=1800, private, proxy-revalidate"
ExpiresDefault "access plus 30 minutes"
</FilesMatch>

AuthUserFile \$htpasswd_file_path
AuthGroupFile /dev/null
AuthName "Private"
AuthType Basic
Require valid-user

EOT;

    }
}


?>
