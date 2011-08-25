<?php

// If we haven't been installed...
if (!file_exists('includes/config.php')) {
    require('includes/install.php');
    exit;
}


function display_page($sections)
{
    echo render_template("t_head", array('title' => TITLE));
    
    foreach ($sections as $s)
    {
        $s->render();
    }
    
    echo render_template("t_popup");
    echo render_template("t_foot");
}


require_once("includes/settings.php");
require_once("includes/logic.php");
require_once("includes/Layer.php");
require_once("includes/presentation.php");

// Initialize database objects
$databases = array();
$domains = array();
$backups = array();
$configurations = array();
$sections = array();

$dbh = null;
connect_to_database($dbh);

if ($dbh)
{
    initialize_databases($dbh, $databases);
    
//    mysql_select_db(DB_DATABASE);
    
    initialize_domains($domains);
    initialize_configurations($dbh, $configurations);
    initialize_backups($dbh, $backups, $configurations);
} else {
    echo '<p class="error">Failed to connect to database server.  Server may be down or database settings are incorrect.</p>';
}
    
// Create Settings layer
$settings_layers = array();

$settings_layers[] = new Layer(
    "Database Settings",
    null, null,
    new Layer(null,
        null,
        render_template(
            "t_settings_database",
            array(
                "db_hostname" => DB_HOSTNAME,
                "db_username" => DB_USERNAME,
                "db_database" => DB_DATABASE
            )
        )
    )
);

$settings_layers[] = new Layer(
    "S3 Settings",
    null, null,
    array(
        new Layer(null,
            null,
            render_template(
                "t_settings_s3",
                array(
                    "s3_enabled" => S3_ENABLED ? ' checked="checked"' : "",
                    "s3_remote_path" => S3_REMOTE_PATH,
                    "s3_access_key" => S3_ACCESS_KEY,
                    "s3_use_https" => S3_USE_HTTPS ? ' checked="checked"' : ""
                )
            )
        )
    )
);

$settings_layers[] = new Layer(
    "Login Settings",
    null, null,
    array(
        new Layer(null,
            null,
            render_template(
                "t_settings_login",
                array(
                    "login_username" => LOGIN_USERNAME,
                )
            )
        )
    )
);

$sections[] = new Layer("Settings",
    render_template("t_settings_head", array('type' => 'settings')),
    render_template("t_settings_foot"),
    $settings_layers);
    
if (!$dbh) {
    display_page($sections);
    exit;
}

// Configuration layers

if ($configurations)
{
    $conf_layers = array();
    
    $existing_config_layers = array();
        
    foreach ($configurations as $c)
    {
        $configuration_names[$c->id] = $c->name;
        $prefix = PREFIX_CONFIG_MODIFY . "-" . $c->name;
        
        // Build a layer for each existing configuration
        $existing_config_layers[] = new Layer(
            $c->name,
            null, null,
            new Layer(null, null,
                render_template("t_configuration", 
                    array(
                        "id" => $c->id,
                        "type" => PREFIX_CONFIG_MODIFY,
                        "prefix" => $prefix,
                        "name" => $c->name,
                        "num_backups" => $c->num_backups,
                        "s3_remote_path" => $c->s3_remote_path,
                        "domains" => 
                            make_selectlist($domains, "domains", 
                                            "Select Domains", $prefix, 
                                            $c->domains),
                        "databases" => 
                            make_selectlist($databases, "databases", 
                                            "Select Databases", $prefix,
                                            $c->databases),
                    ), 
                    array(
                        "show_num_backups" => true,
                        "custom_s3_config" => $c->custom_s3_config,
                        "s3_enabled" => $c->s3_enabled
                    )
                )
            )
        );

/* MDB: TODO        
        $automation_config_layers[] = new Layer(
            $c->name,
            null, null,
            new Layer(null, null
                render_template("t_automation_configuration",
                    array(
                        "id" => $c->id,
                        "type" => PREFIX_AUTOMATION_MODIFY,
                        "prefix" => PREFIX_AUTOMATION_MODIFY . "-" . $c->name,
                        "name" => $c->name,
                        "type" => $a->type
                    )
                )
        );
*/
    }
    
    $conf_layers[] = new Layer("Modify Configurations", null, null, 
                               $existing_config_layers);
    
    // Build "delete configuration" layer
    $conf_layers[] = new Layer(
        "Delete Configurations",
        null, null,
        new Layer(null, null,
            render_template("t_delete_configurations",
                array(
                    "configurations" =>
                        make_selectlist(
                            $configuration_names, "configurations", 
                            "Select Configurations", PREFIX_CONFIG_DELETE
                        ),
                    "type" => PREFIX_CONFIG_DELETE
                )
            )
        )
    );
}

// "New configuration" layer
$conf_layers[] = new Layer(
    "Create New Configuration",
    null, null,
    new Layer(null, null,
        render_template("t_configuration", 
            array(
                "prefix" => PREFIX_CONFIG_NEW,
                "id" => "new",
                "type" => PREFIX_CONFIG_NEW,
                "name" => "",
                "num_backups" => "",
                "s3_remote_path" => "",
                "domains" => 
                    make_selectlist($domains, "domains", "Select Domains", 
                                    PREFIX_CONFIG_NEW),
                "databases" => 
                    make_selectlist($databases, "databases", "Select Databases",
                                    PREFIX_CONFIG_NEW),
            ), 
            array(
                "show_num_backups" => true,
                "custom_s3_config" => false,
                "s3_enabled" => false
            )
        )
    )
);

$sections[] = new Layer("Configurations", null, null, $conf_layers);

// Backups

$backup_layers = array();

// UGLY
if ($configurations)
{
    $backup_config = "";
    
    foreach ($configuration_names as $id => $name)
    {
        $backup_config .= render_template("t_backup_configuration",
            array("prefix" => PREFIX_BACKUP_FROM_CONF, "name" => $name, 
                  "id" => $id)
        );
    }
    
    $backup_layers[] = new Layer(
        "From Configuration",
        null, null,
        new Layer(null, null, $backup_config)
    );
}

$backup_layers[] = new Layer(
    "One-Time Backup",
    null, null,
    new Layer(null, null,
        render_template("t_configuration", 
            array(
                "prefix" => PREFIX_BACKUP_ONETIME,
                "type" => PREFIX_BACKUP_ONETIME,
                "id" => "",
                "name" => "",
                "num_backups" => "",
                "s3_remote_path" => "",
                "domains" => 
                    make_selectlist($domains, "domains", "Select Domains", 
                                    PREFIX_BACKUP_ONETIME),
                "databases" => 
                    make_selectlist($databases, "databases", "Select Databases", 
                                    PREFIX_BACKUP_ONETIME),
            ), 
            array(
                "show_num_backups" => false,
                "custom_s3_config" => false,
                "s3_enabled" => false
            )
        )
    )
);

$sections[] = new Layer("Backup Now", null, null, $backup_layers);

// Backups

if ($backups)
{
    $backup_layers = make_existing_backup_layers(
                        PREFIX_EXISTING_BACKUP, $configurations, $backups);

    $sections[] = new Layer(
        "Existing Backups",
        render_template("t_existing_backup_head", 
            array('prefix' => PREFIX_EXISTING_BACKUP, 
                  'type' => PREFIX_EXISTING_BACKUP)),
        render_template("t_existing_backup_foot", 
            array('prefix' => PREFIX_EXISTING_BACKUP)),
        $backup_layers
    );
}

// Automation!
/*
if ($configurations)
{
    $sections[] = new Layer(
        "Automation",
        render_template("t_automation",
            array()
    );
}
*/
// Finally, render the page!
display_page($sections);

if ($dbh)
    mysql_close($dbh);

?>
