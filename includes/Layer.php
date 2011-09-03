<?php

require_once("render_template.php");

class Layer
{
    private $name = null;
    private $text = null;
    private $head_text = null;
    private $sublayers = array();
    private $panel_layer = false;
    private $icon = null;

    public function __construct($name, $head_text=null, $text=null, $sublayers=null,$icon=null)
    {
        $this->name = $name;
        $this->head_text = $head_text;
        $this->text = $text;
        
        if ($sublayers)
            $this->attach_layers($sublayers);

        $this->icon = $icon;
    }

    public function attach_layers($sublayers)
    {
        if (!$sublayers)
            return;

        if (is_array($sublayers))
        {
            foreach($sublayers as $l)
                $this->sublayers[] = $l;
        } else {
            $this->sublayers[] = $sublayers;
        }
    }

    public function render($layer=0)
    {
        $header_rendered = false;

        if ($this->name)
        {
            $this->panel_layer = true;
            $this->render_header($layer);
            $header_rendered = true;
        }

        if ($this->head_text)
            echo $this->head_text;
        
        if ($this->sublayers) {
            foreach($this->sublayers as $l)
                $l->render($layer + 1);
        }

        if (!$header_rendered)
            $this->render_header($layer);
    
        if ($this->text)
            echo $this->text;

        $this->render_footer();
    }
    
    public function set_head_text($text)
    {
        $this->head_text = $text;
    }
    
    public function set_text($text)
    {
        $this->text = $text;
    }
    
    private function render_header($layer)
    {
        if ($this->panel_layer)
            echo render_template("t_panel_layer_head", 
                array("name"  => $this->name, "layer" => $layer), 
                array(
                    "layer" => $layer,
                    "name"  => $this->name,
                    "icon"  => $this->icon
                )
            );
        else
           echo render_template("t_nopanel_layer_head", array("layer" => $layer));
    }
    
    private function render_footer()
    {
        if ($this->panel_layer)
            echo render_template("t_panel_layer_foot");
        else
            echo render_template("t_nopanel_layer_foot");
    }
}


function t_panel_layer_head($v)
{
    $wrap = $v["layer"] + 1;

    $id = "";
    if ($v['layer'] < 2) {
        $id = preg_replace('/\s/', '-', $v['name']);
        $id = " id='$id'";
    }

    if ($v['icon']) {
        $icon = "<img src='media/images/icons/{$v['icon']}' />";
    }

    return <<<EOT

<div class="wrapper panel\$layer"$id>
    <div class="panel-trigger">
        $icon
        <h$wrap><a href="javascript:void(0);">\$name</a></h$wrap>
    </div>
    <div class="panel">
EOT;
}


function t_panel_layer_foot()
{
    return <<<EOT

    </div>
</div>
EOT;
}

function t_nopanel_layer_head()
{    
    return <<<EOT
    
<div class="wrapper panel\$layer content">
EOT;
}

function t_nopanel_layer_foot()
{
    return <<<EOT
    
</div>
EOT;
}

?>
