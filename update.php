<?php

require_once("includes/settings.php");
require_once("includes/classes.php");
require_once("includes/logic.php");

/*
set_time_limit(0);
ini_set('mysql.connect_timeout', -1);
ini_set('default_socket_timeout', -1);
ini_set('max_execution_time', -1);
*/

// Prepare ajax result
function make_result($success, $text, $debug=null)
{
    $result = array();
    
    $result['success'] = $success;
    $result['text'] = $text;
    
    if ($debug != null)
        $result['debug'] = $debug;
    
    return $result;
}


// Return ajax result
function send_result(&$result)
{
    echo json_encode($result);
}


$result = array();
$type = $_POST['type'];
unset($_POST['type']);

// DEBUG
$result['success'] = true;

// Validate post information
if (!$_SERVER['REQUEST_METHOD'] || $_SERVER['REQUEST_METHOD'] != 'POST' ||  !isset($type))
{
/* Check referrer, if from index.php then... */
    /*
        $result['success'] = false;
        $result['text'] = "Invalid post.";
        echo json_encode($result);
    else
    {
    */
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
    /*
    }
    */
    exit;
}

$databases = array();
$backups = array();
$configurations = array();
$dbh = null;

if ($type != "settings") {
    connect_to_database($dbh);
    if (!$dbh)
    {
        $result = make_result(false, "Unable to connect to database.", mysql_error($dbh));
        send_result($result);
        return;
    }
}

$debug = " ";
$result = null;

switch ($type)
{
    case PREFIX_CONFIG_MODIFY:
        $obj = new Configuration($_POST);
        if (save_configuration($dbh, $obj, false, $debug))
            $result = make_result(true, "Configuraton saved.", $debug);
        else
            $result = make_result(false, "Failed to save configuration.", $debug);

        break;

    case PREFIX_CONFIG_NEW:
        $configuratons = array();
        initialize_configurations($dbh, $configurations);

        foreach ($configurations as $c)
        {
            if ($c->name == $_POST['name'])
            {
                $result = make_result(false, "A configuration with that name already exists.");
                send_result($result);
                exit;
            }
        }

        unset($_POST['id']);
        $obj = new Configuration($_POST);

        if (save_configuration($dbh, $obj, true, $debug))
            $result = make_result(true, "Configuration saved.", $debug);
        else
            $result = make_result(false, "Failed to save configuration.", $debug);

        break;

    case PREFIX_CONFIG_DELETE:
        if (!$_POST['configurations'])
            $result = make_result(false, "No configurations were selected.");
        else
        {
            if (delete_configurations($dbh, $_POST['configurations'], $debug))
                $result = make_result(true, "The selected configurations were deleted.", $debug);
            else
                $result = make_result(false, "Could not delete configurations.", $debug);
        }

        break;

    case PREFIX_BACKUP_FROM_CONF:
    case PREFIX_BACKUP_ONETIME:
        $configuration = null;
    
        if ($type == "backup")
        {
            $configuratons = array();
            initialize_configurations($dbh, $configurations);
        
            $configuration = $configurations[$_POST['id']];
        } else {
            $configuration = new Configuration($_POST);
        }

        if (!$configuration)
            $result = make_result(false, "Failed to load configuration for backup.");
        else
        {
            if (create_backup($dbh, $configuration, $output, $debug))
            {
                $result = make_result(true, "Backup created successfully.", $debug);
            }
            else 
            {
                $lines = explode("\n", $output);
                $line = end($lines);
                $result = make_result(false, "Backup failed:<br />\n$line", $debug);
            }
        }

        break;

    case PREFIX_EXISTING_BACKUP:
        $list = array();

        foreach($_POST as $config) {
            foreach($config as $backup) {
                if ($backup != 'all')
                    $list[] = $backup;
            }
        }
        
        if (!$list)
            make_result(true, "No backups were selected.");
        else
        {
            $configurations = array();
            initialize_configurations($dbh, $configurations);
            
            $backups = array();
            initialize_backups($dbh, $backups, $configurations);
        
            $debug = implode(", ", $list) . "\n";
            
            if (delete_backups($dbh, "backups", $list, $backups, $debug))
                $result = make_result(true, "Successfully deleted backups.", $debug);
            else
                $result = make_result(false, "An error occurred while deleting backups.", $debug);
        }

        break;
        
    case "settings":
        $error_text = "";
    
        if (!write_config_file($_POST, $error_text))
        {
            if (!$error_text)
                $result = make_result(false, "Failed to write config file.");
            else
                $result = make_result(false, $error_text);
        } else
            $result = make_result(true, "Updated configuration file.");
            
        break;
        
    case "s3":
        $id = $_POST['id'];

        $configurations = array();
        initialize_configurations($dbh, $configurations);

        $backups = array();
        initialize_backups($dbh, $backups, $configurations);

        $backup = $backups[$id];
        $configuration = $backup->configuration;

        $error_text = "";
        
        if (upload_to_s3($dbh, $configuration, $backup, $error_text, $debug))
        {
            $result = make_result(true, "{$backup->filename} was uploaded to S3.");
        } else {
            $result = make_result(false, $error_text);
        }
        
        break;

    default:
        $result = make_result(true, "Not yet implemented :(", $type);
        break;
}

send_result($result);

?>
