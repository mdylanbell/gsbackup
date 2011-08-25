<?php

require_once("includes/settings.php");

$path = BACKUP_PATH;
$filename = preg_replace('/^[^\w-_\.]*/', '', $_GET['file']);
$error = false;

$full_path = $path . $filename;

$fsize = filesize($full_path);
$path_parts = pathinfo($full_path);
$ext = strtolower($path_parts["extension"]);

if ($ext != "bz2")
    $error = true;
else
{
    if ($fd = fopen ($full_path, "r"))
    {
        // extension specific information
        header("Content-type: application/x-bzip-compressed-tar");
        header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\"");
    
        // general file information
        header("Content-length: $fsize");
        header("Cache-control: private");
        
        while(!feof($fd))
        {
            $buffer = fread($fd, 2048);
            echo $buffer;
        }
        
        fclose ($fd);
    } else
        $error = true;
}

if ($error)
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");

exit();

?>