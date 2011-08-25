<?php

class Backup
{
    public $id = null;
    public $configuration;
    public $filename = "";
    public $filesize = "";
    public $date_created = ""; // = ??
    public $uploaded_to_s3 = false;
    public $s3_remote_path = null;
    public $successful = false;
    
    function __construct($array)
    {
        $attrs = array_keys(get_object_vars($this));
        
        foreach ($attrs as $attr) {
            if (isset($array[$attr]))
                $this->$attr = $array[$attr];
        }
    }
}


class Configuration
{
    public $id = null;
    public $name = "";
    public $num_backups = 0;
    public $databases = array();
    public $one_database_file = false;
    public $domains = array();
    public $custom_s3_config = false;
    public $s3_enabled = null;
    public $s3_remote_path = null;
    public $last_attempted_backup = null;

    function __construct($array = array())
    {
        $attrs = array_keys(get_object_vars($this));

        foreach ($attrs as $attr)
            if (isset($array[$attr]))
                $this->$attr = $array[$attr];
    }
}

class Schedule
{
    public $type = "";
    public $time = null;
    public $day = "";
    public $configuration = null;
}

?>