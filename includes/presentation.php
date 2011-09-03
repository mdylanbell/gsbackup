<?php

require_once("settings.php");
require_once("render_template.php");

function make_selectlist($array, $type, $label, $prefix, $checked_array = array())
{
    $checked = false;

    /* Determine if "all" is selected and should be checked */
    if ($checked_array[0] == "all")
    {
        $checked = true;
    }
    
    $select_list = render_template('t_selectlist_header',
            array('type' => $type, 'label' => $label, 'prefix' => $prefix),
            array('checked' => $checked)
        );

    /* Determine if each individual item is checked or not */
    foreach($array as $item)
    {
        $checked = false;

        if ($checked_array[0] == "all")
            $checked = true;
        elseif ($checked_array)
        {
            foreach($checked_array as $s)
            {
	          if ($s == $item)
	               $checked = true;
            }
        }

        $select_list .= render_template('t_selectlist_item',
                array('type' => $type, 'prefix' => $prefix, 'item' => $item),
                array('checked' => $checked)
            );
    }

    $select_list .= render_template("t_selectlist_foot");
    
    return $select_list;
}


function make_existing_backup_layers($prefix, &$configurations, &$backups)
{
    $layers = array();

    $sorted_backups = array();
    $names = array();

    foreach ($backups as $b) {    
        if (!$b->configuration)
        {
            $name = "Miscellaneous";
        } else
            $name = $b->configuration->name;

        if (!in_array($name, $names))
            $names[] = $name;

        if (!$sorted_backups[$name])
            $sorted_backups[$name] = array();
            
        $sorted_backups[$name][] = $b;
    }

    $s3_status = 0;

    foreach ($names as $n)
    {
/*TODO: Finish s3 status / button
        if ($configurations[$n]) {
            $c = $configuration[$n];
            
            if (S3_ENABLED || ($c->custom_s3_config && $c->s3_enabled))
            {
                $s3_status = true;
            }
        }
        
        if ($n == "Miscellaneous")
*/
        
        $layers[] = make_existing_backup_configuration_layer(
                        $prefix, $n, $sorted_backups[$n]
        );
    }

    return $layers;
}


function make_existing_backup_configuration_layer($prefix, $name, $backups)
{
    $css_name = preg_replace('/[\._\s]/', '-', $name);
    
    $text = render_template("t_existing_backup_table_head",
        array('prefix' => $prefix, 'name' => $css_name));

    foreach($backups as $b)
    {
        if ($b->uploaded_to_s3)
            $s3status = "Uploaded to S3";
        else
            $s3status = "<input type=\"button\" class=\"s3-upload-button\" name=\"{$b->filename}-s3-upload\" value=\"Upload\" backup-id=\"{$b->id}\" />";
            
        /* Pretty-up the filesize */
        $b->filesize = format_bytes($b->filesize);
        
        $text .= render_template("t_existing_backup_table_item", 
                array('prefix' => $prefix, 'name' => $css_name, 'b'=> $b, 's3status' => $s3status));
    }

    $text .= render_template("t_existing_backup_table_foot");

    return new Layer($name, null, null, new Layer(null, null, $text));
}


function format_bytes($bytes)
{
    if (!empty($bytes)) {
        $s = array('bytes', 'KB', 'MB', 'GB');
        $e = floor(log($bytes)/log(1024));
 
//        $output = sprintf('%.2f '.$s[$e], ($bytes/pow(1024, floor($e))));
        $output = sprintf('%.' . ($e == 0 ? 0 : 2) . 'f '. $s[$e], ($bytes/pow(1024, floor($e))));

        return $output;
    }
}

?>
