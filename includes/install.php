<?php

require_once('settings.php');
require_once('Layer.php');
require_once('render_template.php');
require_once('logic.php');

function make_error($text)
{
    return "<div class=\"error\">$text</div>";
}

if (!$_SERVER['REQUEST_METHOD'])
    exit;
    
$errors = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $expected = array(
        'dbhost' => 'Database Host', 
        'database' => 'Database', 
        'dbuser' => 'Database User', 
        'dbpass' => 'Database Password',
        'dbpass2' => 'Database Password Confirmation',
        'settings-login-username' => 'Username',
        'settings-login-password' => 'Login Password',
        'settings-login-password2' => 'Login Password Confirmation',
    );

    $error = "";
    
    foreach ($expected as $key => $label)
    {
        if (!$_POST[$key])
        {
            if (!$error)
                $error = "<strong>All fields are required.  Please specify</strong><ul>\n";
            $error .= "<li>" . $label . "</li>\n";
        }        
    }
    
    if ($error)
        $errors[] = $error . "</ul>";

    if ($_POST['dbpass'] != $_POST['dbpass2']) {
        $errors[] = "Database passwords do not match.<br />\n";
    }
    
    if ($_POST['settings-login-password'] != $_POST['settings-login-password2']) {
        $errors[] = "Login passwords do not match.<br />\n";
    }
    
    if (!$errors)
    {
        $link = @mysql_connect($_POST['dbhost'], $_POST['dbuser'], $_POST['dbpass']);
        if (!$link)
        {
            $errors[] = "Could not establish a connection to the database with information provided.";
        } else {
            mysql_close($link);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' || $errors)
{
    $db_layer = new Layer(
        null,
        null,
        render_template("t_settings_database",
            array(
                "db_hostname" => $_SERVER['DATABASE_SERVER'],
                "db_database" => "db" . SITE_ID . "_gsbackup",
                "db_username" => "db" . SITE_ID
            )
        )
    );
    
    $login_layer = new Layer(
        null,
        null,
        render_template("t_settings_login")
    );
    
    $s3_layer = new Layer(
        null,
        null,
        render_template("t_settings_s3", null)
    );
    
    // Display

    echo render_template("t_head");

    if ($errors)
    {
        echo '<div id="errors">' . "\n";
        foreach ($errors as $e)
            echo make_error($e);
        echo "</div>\n";
    }

?>

<h1>(gs) backup Installer</h1>

<br /><br />

<div class="installer-text">
<p>
<h2>This tool is not provided by (mt) Media Temple</h2>
<h2>This tool is not supported by (mt) Media Temple</h2>

<br /><br />

Notes:<br />
<br />
Database and Login Settings are required.  The Login Settings are used to secure access to this tool after installation.  If you have an Amazon S3 account, you can find your S3 access and secret keys under the "Security Credentials" section in your Amazon account settings.<br /><br />
You can change all of these settings later in the "Settings" panel.<br />

</div>

<br /><br />

<form name="install" id="form_install" method="post" action="">

<h2>Database Settings</h2>

<?php
    $db_layer->render();
?>

<br />
<h2>Login Settings</h2>

<?php
    $login_layer->render();
?>

<br />
<h2>Amazon S3 Settings (optional)</h2>

<?php
    $s3_layer->render();
?>

    <br />

    <input type="submit" value="Install" />
</form>

<br />
<br />

<div class="installer-text">

Project Home: <a href="http://gsbackup.org">gsbackup.org</a><br />
Author: <a href="mailto:matt@gsbackup.org">Matthew Bell</a><br />
Bug tracker: <a href="http://bugs.mindthread.org">bugs.mindthread.org</a><br />
<br />
github and/or Google code coming soon!<br />
</p>
</div>

<?php
    
    echo render_template("t_foot");
    
    exit;
}

echo render_template("t_head", array('title' => "Install " . VERSION . " - " . TITLE));
echo "Please wait...<br />\n";

// Information posted successfully
$output = array();
$error = false;

$command = "/usr/bin/mysql -h {$_POST['dbhost']} -u {$_POST['dbuser']} -p{$_POST['dbpass']} {$_POST['database']} < includes/gsbackup.sql";

exec($command, $output, $return);
if ($return) {
    echo "Failed to insert mysql data to database:<br />\n";
    foreach ($output as $o)
        echo "$o<br />\n";
    $error = true;
}

if ( !$error ) {
    exec("install/install.sh --with-s3", $output, $return);
    if ($return) {
        echo "Failed running install.sh:<br />\n";
        foreach($output as $o)
            echo "$o<br />\n";
        $error = true;
    }
}

if ( !$error ) {
    if (!write_config_file($_POST, $error_msg)) {
        echo "Failed to write config file: $error_msg";
        $error = true;
    }
}

if ($error)
{
    echo render_template("t_foot");
    exit();
}

?>

Install complete!
Enter the username and password you specified in "Login Settings" to access the backup tool.
<script type="text/javascript">
$(location).attr('href', "index.php");
</script>

<?php

echo render_template("t_foot");

?>
