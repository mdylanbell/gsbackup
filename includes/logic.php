<?php

require_once("settings.php");
require_once("classes.php");
require_once("render_template.php");

function connect_to_database(&$dbh)
{
    $dbh = @mysql_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, true);
    
    if (!$dbh)
       return null;

    mysql_select_db(DB_DATABASE, $dbh);
}


function initialize_databases($dbh, &$databases)
{
    $db_list = mysql_list_dbs($dbh);
    while ($row = mysql_fetch_object($db_list)) {
        if ($row->Database != "information_schema") {
            $databases[] = $row->Database;
        }
    }
    
    sort($databases);
}


function compare_domains($a, $b)
{
    $pattern = '/[^.]+\.[^\.]+$/';
    preg_match($pattern, $a, $a_domain);
    preg_match($pattern, $b, $b_domain);

    return strcmp($a_domain[0], $b_domain[0]);
}


function initialize_domains(&$domains)
{
    global $ignore_domains;

    $dh = opendir(DOMAINS_PATH);
    while (($filename = readdir($dh)) != false)
    {
        if (is_dir(DOMAINS_PATH . $filename) && $filename != "." && 
            $filename != "..")
        {
            $ignore = false;

            foreach($ignore_domains as $i)
                if ($filename == $i)
                    $ignore = true;
                    
            if (!$ignore)
                $domains[] = $filename;
        }
    }

    // Sort by domain in alphabetical order (this groups subdomains)
    usort($domains, "compare_domains");
}


function initialize_configurations($dbh, &$configurations)
{
    $db_res = mysql_query("SELECT * FROM configurations", $dbh);
    
    if (!$db_res)
        return;

    while ($row = mysql_fetch_array($db_res, MYSQL_ASSOC))
    {
        if ($row['databases'])
            $row['databases'] = explode(",", $row['databases']);
            
        if ($row['domains'])
            $row['domains'] = explode(",", $row['domains']);
        
        $configurations[$row['id']] = new Configuration($row);
    }
    
    mysql_free_result($db_res);
}


function initialize_backups($dbh, &$backups, &$configurations)
{
    // TODO: Add backup-type (.tar.bz2 currently) files to "Miscellaneous"
    /*
    $dh = opendir(BACKUP_PATH);
    while (($filename = readdir($dh)) != false) {
        if (!is_dir(BACKUP_PATH . $filename)) {}
    }
    */

    $db_res = mysql_query("SELECT * FROM backups ORDER BY id", $dbh);

    if (!$db_res)
        return;

    while ($row = mysql_fetch_array($db_res, MYSQL_ASSOC))
    {
        $row['s3_state'] = ($row['s3_state'] == "1") ? true : false;
        $row['success'] = ($row['s3_state'] == "1") ? true : false;
        $row['configuration'] = $configurations[$row['configuration']];

        $backups[$row['id']] = new Backup($row);
    }
    
    if ($db_res)
        mysql_free_result($db_res);
}


function write_config_file($array, &$error_msg)
{
    // Determine if passwords are already set.  If so, we don't *have* to 
    // have them as input.  This allows settings to be changed without
    // re-entering or changing passwords

    if (defined("DB_PASSWORD") && $array["dbpass"] == "" && 
        $array["dbpass2"] == "")
    {
        $array["dbpass"]  = DB_PASSWORD;
        $array["dbpass2"] = DB_PASSWORD;
    }
    if (defined("S3_SECRET_KEY") && $array["settings-s3-secretkey"] == "")
        $array["settings-s3-secretkey"] = S3_SECRET_KEY;

    $expected = array(
        'dbhost' => 'Database Host',
        'database' => 'Database',
        'dbuser' => 'Database User',
        'dbpass' => 'Database Password',
        'dbpass2' => 'Confirmation Password'
    );

    $s3_enabled = "false";
    $s3_remote_path = "";
    $s3_use_https = "false";
    $s3_access_key = "";
    $s3_secret_key = "";
    
    $write_s3cfg = false;
    
    $login_username = "";
    $login_password = "";
    
    $write_htpasswd = false;

    // Process mandatory settings
    foreach ($expected as $key => $label)
    {
        if (!$array[$key])
        {
            if (!$errors)
            {
                $error_msg = "All database fields required.  " .
                             "Please specify:<br />\n";
            }

            $errors = true;
            $error_msg .= $label . "<br />\n";
        }
    }

    // Process optional settings like s3
    if ( $array['settings-s3-enabled'] && !($array['settings-s3-remotepath'] && 
         $array['settings-s3-accesskey'] && 
        ($array['settings-s3-secretkey'] || S3_SECRET_KEY)))
    {
        $errors = true;
        $error_msg .= "You must specify all S3 settings to enable S3.<br />\n";
    }

    if ( $array['settings-s3-enabled'] && $array['settings-s3-remotepath'] && 
         $array['settings-s3-accesskey'] && 
        ($array['settings-s3-secretkey'] || S3_SECRET_KEY))
    {
        $write_s3cfg = true;
    }

    if ($array['settings-s3-enabled'])
        $s3_enabled = "true";
    if ($array['settings-s3-remotepath'])
        $s3_remote_path = $array['settings-s3-remotepath'];
    if ($array['settings-s3-usehttps'])
        $s3_use_https = "True";
    if ($array['settings-s3-accesskey'])
        $s3_access_key = $array['settings-s3-accesskey'];
    if ($array['settings-s3-secretkey'])
        $s3_secret_key = $array['settings-s3-secretkey'];

    if ($array['dbpass'] != $array['dbpass2']) {
        $errors = true;
        $error_msg .= "Database passwords do not match.<br />\n";
    }
    
    if ($array['settings-login-username'])
        $login_username = $array['settings-login-username'];
    if ($array['settings-login-password'])
        $login_password = $array['settings-login-password'];
        
    // Process login settings
    if (!$array['settings-login-username'])
    {
        $errors = true;
        $error_msg = "You must specify a username.<br />\n";
    }
    
    if ( $array['settings-login-password'] != $array['settings-login-password2'])
    {
        $errors = true;
        $error_msg .= "Login passwords do not match.<br />\n";
    } else {
        $login_username = $array['settings-login-username'];
        $login_password = $array['settings-login-password'];
    }

    if ($errors)
        return false;

    if ($array['settings-login-password'] && $array['settings-login-password2'])
        $write_htpasswd = true;

    $return = 0;

    // Write config.php
    $fh = fopen('includes/config.php', 'w') or 
        die ("Failed to open install/config.php for writing");
    
    fwrite($fh, render_template("t_config_file",
        array(
            'db_hostname'    => $array['dbhost'],
            'db_database'    => $array['database'],
            'db_username'    => $array['dbuser'],
            'db_password'    => $array['dbpass'],
            's3_enabled'     => $s3_enabled,
            's3_remote_path' => $s3_remote_path,
            's3_access_key'  => $s3_access_key,
            's3_secret_key'  => $s3_secret_key,
            's3_use_https'   => $s3_use_https,
            'login_username' => $login_username,
        ))
    ) or die ("Unable to write to includes/config.php");
    
    fclose($fh);

    // Write .s3cfg if applicable
    if ($write_s3cfg)
    {
        $fh = fopen(S3CFG_FILE_PATH, 'w') or 
            die ("Failed to open S3 configuration file (" .
                S3CFG_FILE_PATH . ") for writing");
        
        fwrite($fh, render_template("t_s3cfg_file",
            array(
                's3_access_key' => $s3_access_key,
                's3_secret_key' => $s3_secret_key,
                's3_use_https'  => $s3_use_https,
            ))
        ) or die ("Unable to write to S3 configuration file (" . 
                  S3CFG_FILE_PATH . 
                  ")");
                
        fclose($fh);
    }
    
    // Write .htpasswd if applicable
    if ($write_htpasswd)
    {
        $fh = fopen(HTPASSWD_FILE_PATH, 'w') or 
            die ("Failed to to open .htpasswd file (" . HTPASSWD_FILE_PATH . 
                 ") for writing");

        fwrite($fh,
            render_template("t_htpasswd_file",
                array(
                    'login_username' => $login_username,
                    'login_password' => crypt($login_password),
                )
            )
        ) or die ("Unable to write to .htpasswd file (" . HTPASSWD_FILE_PATH . ")");

        fclose($fh);

        $fh = fopen(HTACCESS_FILE_PATH, 'w') or 
            die ("Failed to open .htaccess file (" . HTACCESS_FILE_PATH . 
                 ") for writing");

        fwrite($fh,
            render_template("t_htaccess_file",
                array('htpasswd_file_path' => HTPASSWD_FILE_PATH,)
            )
        ) or die ("Unable to write to .htaccess file (" . HTACCESS_FILE_PATH . ")");

        fclose($fh);
    }

    return true;
}

/******************************************************************************
 * Database functions
 ******************************************************************************/
 
function save_database_object($dbh, $table, &$obj, $new, &$debug=null)
{
    if (!$dbh || !$table || !$obj)
        return false;

    $sql = "";
    $vars = get_object_vars($obj);

    if ($new)
        $sql = make_insert_statement($table, $vars);
    else
        $sql = make_update_statement($table, $vars);
        
    if ($sql && $debug != null)
        $debug .= "$sql\n";

    if (!mysql_ping($dbh))
    {
        mysql_close($dbh);
        connect_to_database($dbh);
    }
   
    return mysql_query($sql, $dbh);
}


function make_insert_statement($table, &$vars)
{
    unset($vars['id']);

    $keys = array_keys($vars);
    $p_keys = array();
    foreach($keys as $k)
    {
        $p_keys[] = "`$k`";
    }
    $keys_list = implode(", ", $p_keys);

    $values = array_values($vars);
    $p_values = array();
    foreach($values as $v)
    {
        if (is_null($v))
            $p_values[] = "NULL";
        else
        {
            $p_values[] = "'" . make_mysql_var($v) . "'";
        }
    }

    $values_list = implode(", ", $p_values);

    return "INSERT INTO $table ($keys_list) VALUES ($values_list)";
}


function make_update_statement($table, $vars)
{
    $return = "UPDATE $table SET ";
    
    $set = array();
    foreach($vars as $k => $v)
    {
        $set[] = "`$k`='" . make_mysql_var($v) . "'";
    }
    
    $return .= implode(", ", $set) . " WHERE `id`='{$vars['id']}'";
    
    return $return;
}


function make_mysql_var($v)
{
    global $dbh;
    $all = false;

    if (is_array($v))
    {
        foreach($v as $i)
        {
            if ($i == 'all')
            {
                $all = true;
                break;
            }
        }
        
        if ($all)
            $return = "all";
        else
            $return = implode(",", $v);
    }
    elseif (is_string($v) || is_numeric($v))
        $return = $v;
    elseif (is_null($v))
        $return = "NULL";
    elseif (is_bool($v))
        $return = $v ? "1" : "0";
        
    $v = trim($v);
    $v = mysql_real_escape_string($v, $dbh);
        
    return $return;
}


function save_configuration($dbh, $configuration, $new, &$debug=null)
{
    return save_database_object($dbh, "configurations", $configuration, $new, 
                                $debug);
}

/******************************************************************************
 * Update functions
 ******************************************************************************/

function delete_configurations($dbh, $data, &$debug=null)
{    
    $where_array = array();
    
    foreach ($data as $k => $v)
    {
        $where_array[] = "`name`='$v'";
    }
    
    $where = implode(" OR ", $where_array);
    $sql = "DELETE IGNORE FROM configurations WHERE $where";
    if ($debug != null)
        $debug = $sql;
    
    return mysql_query($sql, $dbh);
}


function create_backup($dbh, $configuration, &$output, &$debug=null)
{
    $exec_output = array();
    $return = 0;
    $domains_opt = "";
    $databases_opt = "";
    $s3_opt = "";
    
    $uploaded_to_s3 = false;

    // Generate string for domain information to pass to the backup script
    if ($configuration->domains)
    {
        $domains_opt = " -C " . DOMAINS_PATH . " ";

        // Did they select all domains?
        if ($configuration->domains[0] == "all")
        {
            $domains = array();
            initialize_domains($domains);
        } else
            $domains = $configuration->domains;

        $domains_opt .= implode(" ", $domains);
    }
    
    // Generate string for database information to pass to backup script
    if ($configuration->databases)
    {
        $databases_opt = " -u " . DB_USERNAME . " -p " . DB_PASSWORD;

        // Add all databases
        if ($configuration->databases[0] == "all")
        {
            $databases = array();
            initialize_databases($dbh, $databases);
        } else 
            $databases = $configuration->databases;

        foreach ($databases as $db)
            $databases_opt .= " -d $db";
    }

    
    // Generate string for number of backups to keep
    if ($num_backups = $configuration->num_backups)
    {
        $num_backups_opt = " -N $num_backups";
    }
    
    // Generate string for S3
    $custom_s3_enabled = ($configuration->custom_s3_config && 
                          $configuration->s3_enabled);
                          
    $custom_s3_disabled = ( $configuration->custom_s3_config && 
                           !$configuration->s3_enabled);
                           
    if ($custom_s3_enabled || (S3_ENABLED && !$custom_s3_disabled))
    {
        $uploaded_to_s3 = true;
    
        $s3_remote_path = null;
    
        if ($custom_s3_enabled)
            $s3_remote_path = $configuration->s3_remote_path;
        else
            $s3_remote_path = S3_REMOTE_PATH;
            
        $s3_opt = " -S -s $s3_remote_path";
    }

    // Finalize command string
    $label = preg_replace('/[^' . ALLOWED_LABEL_CHARACTERS . ']/', '_', 
                          $configuration->name);

    $command = BACKUP_SCRIPT_PATH . 
                " -l $label$num_backups_opt$databases_opt$s3_opt$domains_opt";
    
    if ($debug != null) $debug .= "$command\n";
    exec($command, $exec_output, $return);
    
    $output = implode("\n", $exec_output);
    if ($debug != null) $debug .= "$output\n";
    
    // bash exit 0 = success.  If successful, add to backup table
    if (!$return)
    {
        // Catch the filename from the script output.
        $pattern = BACKUP_PATH . "(.*\.tar\.bz2)";
        $filename = "";
        foreach ($exec_output as $o)
        {
            preg_match("~$pattern~", $o, $matches);
            if ($matches[1])
            {
                $filename = $matches[1];
                break;
            }
        }

        if (!create_backup_entry($dbh, $filename, $configuration->id, 
            $uploaded_to_s3, $debug))
        {
            if ($debug != null)
                $debug .= "Failed to create backup entry: " . mysql_error($dbh);
        }

        prune_backups($dbh, $configuration);
    }
    
    return !$return;
}


function create_backup_entry($dbh, $filename, $configuration_id, 
                             $uploaded_to_s3=false, &$debug=null)
{   
    $data = @stat(BACKUP_PATH . $filename);
    if (!$data && $debug != null)
        $debug .= "Could not stat 'BACKUP_PATH$filename'\n";
    
    $array['configuration'] = $configuration_id;
    $array['filename'] = $filename;
    $array['filesize'] = $data['size'];
    $array['date_created'] = date("Y-m-d, g:ia", $data['ctime']);
    $array['uploaded_to_s3'] = $uploaded_to_s3;
    $array['success'] = true;
    
    $backup = new Backup($array);

    return save_database_object($dbh, "backups", $backup, true, $debug);
}


function prune_backups($dbh, $configuration)
{
    $sql = "SELECT id FROM backups WHERE backups.configuration = " . 
           "$configuration->id ORDER BY backups.id desc limit " .
           "$configuration->num_backups";
           
    $result = mysql_query($sql, $dbh);
    
    if (!$result)
        return false;
        
    $keep_ids = array();
    while ($row = mysql_fetch_array($result))
    {
        $keep_ids[] = $row[0];
    }
    
    mysql_free_result($result);

    $ids_string = implode(",", $keep_ids);
    $sql = "DELETE FROM backups WHERE backups.configuration = " .
           "$configuration->id and backups.id not in ($ids_string)";
    $result = mysql_query($sql, $dbh);
    
    if (!$result)
        return false;
}


function delete_backups($dbh, $table, &$list, &$backups, &$debug=null)
{
    $return = true;

    // Check inputs
    if (!$list || !$dbh || !$table)
        return false;
        
    foreach ($list as $b)
    {
        // Delete backup from backup table
        $sql = "DELETE IGNORE FROM $table WHERE `id`='$b'";
        if ($debug != null) $debug .= "$sql\n";
        
        if (!mysql_query($sql, $dbh))
            $return = false;
        
        // Delete backup from filesystem
        @unlink(BACKUP_PATH . $backups[$b]->filename);
    }
    
    return $return;
}

/******************************************************************************
 * S3 functions
 ******************************************************************************/

function validate_s3_settings()
{
    return (S3_REMOTE_PATH && S3_ACCESS_KEY && S3_SECRET_KEY);
}

function upload_to_s3($dbh, $configuration, $backup, &$error_text, &$debug)
{
    if (!validate_s3_settings())
    {
        $error_text = "S3 configuration is incomplete.  " .
                      "Please fill out all S3 settings.";
        return false;
    }

    $s3_path = "";

    if ($configuration->custom_s3_config && $configuration->s3_remote_path)
        $s3_path = $configuration->s3_remote_path;
    else if (S3_REMOTE_PATH)
        $s3_path = S3_REMOTE_PATH;
    else
    {
        $error_text = "No S3 configuration found for configuration or in " .
                        "settings.  I don't know where to upload.";
    }

    if (!$s3_path)
        return false;

    $s3_path = preg_replace("/^[\/. ]*/", '', $s3_path);
        
    $command = "export PYTHONPATH=" . PYTHONPATH . "; " . 
        S3CMD_FILE . " -c " . S3CFG_FILE_PATH . " -H put " . 
        BACKUP_PATH . "/{$backup->filename} s3://$s3_path";
    $debug .= "command = '$command'\n";

    $output = array();

    exec($command, $output, $result);

    if ($result)
    {
        $output = implode('\n', $output);
        $error_text = "S3 upload failed: $output";

        return false;
    }
    
    $backup->uploaded_to_s3 = true;
    // This is hacky -- replace configuration object with configuration id :(
    $backup->configuration = $configuration->id;
    
    save_database_object($dbh, "backups", $backup, false, $debug);
    
    return true;
}


?>
