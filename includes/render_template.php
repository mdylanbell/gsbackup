<?php
require_once("templates.php");

function render_template($func, $c=null, $vars=null)
{
    if ($c)
        foreach($c as $k => $v) {
            $$k = $v;
        }
    
    $___val = $func($vars);
    
    eval("\$return = \"" . str_replace('"', '\\"', $___val) . "\";");
    
    return $return;
}

?>