$(document).ready(function(){
    // jQuery UI button()ize buttons
    $( "input:submit, button" ).button();

    // Do something interesting with hover and focus
    /*
     * Temprarily disabled -- under development
     *

    $(".panel-trigger").focusin(function(){
        set_panel_icon( $(this) );
    });
    $(".panel-trigger").hover(function(){
        set_panel_icon( $(this) );
    }, function() {
        $(this).find('a').css('color', 'black');
    });

    function set_panel_icon($trigger) {
        var $target = $trigger.find('a');
        var $panel  = $trigger.next('.panel');

        var is_open = !$panel.is(":hidden");

        if (is_open) {
            $target.css('color', 'red');
        } else {
            $target.css('color', 'green');
        }
    }

    $(".panel-trigger").focusout(function(){
        $(this).find('a').css('color', 'black');
    });

    * End temporary disabling
    */

    $(".panel-trigger").click(function(){
        var $me = $(this);
        $me.next('.panel').slideToggle("fast"
            /* Testing for hover and focus effects
            , function() {
                if ($me.find('a').css('color') != 'rgb(0, 0, 0)') {
                    set_panel_icon( $me );
                }
            }
            */
        );
    });
   
    // Disable 'submit' button on 'install' page after submitted
    $('#form_install').submit(function(){
        $('input[type=submit]', this).attr('disabled', 'disabled');
    });


    //  Open or close custom s3 flyout when selection is made
    $("select").change(function(){
       $(this).closest('li').find('.s3-options-panel').slideToggle('fast');
    });

    // When page loads, expand any custom s3 configuration panels
    $("select").each(function() {
        if ($(this).val() == "1") {
            $(this).next('.panel').css("display", "block");
        }
    });

    // Toggle checkboxes when "all" is selected
    $("input[type='checkbox'][value='all']").change(function() {
            var name = $(this).attr("name");
            var value = $(this).attr("checked");

            $(this).closest('form').find("input[name='"+name+"']").each(function() {
                if (value == 'checked' && !$(this).attr('checked')) {
                    $(this).attr("checked", value);
                    $.uniform.update($(this));
                } else if (!value && $(this).attr('checked') == 'checked') {
                    $(this).removeAttr('checked');
                    $.uniform.update($(this));
                }
            });
    });

    // Disable 'all' checkbox if individual checkbox is selected
    $("input[type='checkbox'][value!='all']").change(function() {
        var name = $(this).attr("name");
        var all_obj = $(this).closest('form').find("input[name='"+name+"'][value='all']");

        if (all_obj.attr('checked')) {
            //all_obj.attr('checked', false);
            all_obj.removeAttr('checked');
            $.uniform.update(all_obj);
        }
    });
    
    // Download button
    $(".download-button").click(function(event) {
        event.preventDefault();
        window.location.href = "download/" + $(this).attr('name');
    });
    
    // Upload to S3 button
    $(".s3-upload-button").click(function(event) {
        event.preventDefault();
        var id = $(this).attr("backup-id");

        submit_data = "type=s3&id=" + id;
        dialog_confirm("Upload to S3 now?");
    });

   
    // Create backup
    $(".backup-button").click(function(event) {
        var match = /backup-(\d+)/.exec($(this).attr('id'));
        submit_data = "type=backup&id=" + match[1];
        action = "backup";
        
        dialog_confirm('Backup ' + $(this).attr('name') + ' now?');
    });

    
    // Forms => confirmation dialog and ajax submit
    $("form.confirm").submit(function(event) {
        event.preventDefault();
        submit_data = $(this).serialize();
        
        var type = $(this).children("input[name='type']").val();
        var name = $(this).find("input[name='name']").val();
        var id = $(this).children("input[name='id']").val();
        var text = "";
        var error = false;

        // Validation
        if (type == "mc" || type == "nc" || type == "otb") {
            if (name == "") {
                text = "You must enter a name.";
                error = true;
            } else if (/^[\W]/.test(name)) {
                text = "Name must start with a letter or number.";
                error = true;
            } else if (/[^a-zA-Z0-9\-_\. ]/.test(name)) {
                text = "Name can only contain letters, numbers, spaces, and these characters: ._-";
                error = true;
            }
            
            if (!$(this).find(":checked").val()) {
                if (error) text += "<br />";
                text += "You must select at least one domain or database.";
                error = true;
            }
        }
        
        if (type == "eb") {
            if (!$(this).find(":checked").val()) {
                text = "You must select at least one backup to delete.";
                error = true;
            }
        }
            
        if (error) {
            dialog_error(text);
            return false;
        }

        // Determine text to show in dialog
        if (type == "mc" || type == "nc" ) {
            text = "Save " + name + "?";
        } else if (type == "dc") {
            text = "Delete selected configurations?";
        } else if (type == "otb") {
            action = 'backup';
            text = "Create backup?";
        } else if (type == "settings") {
            text = "Save settings?";
        } else if (type == "eb") {
            text = "Delete selected backups?"
        }
        dialog_confirm(text);

        return false;
    });
});



var submit_data = "";
var action = "";


function do_confirm_action()
{
    if (action == 'backup')
    {
        set_waiting(true);
        dialog_wait('Please wait.  Backup in progress.');
        action == '';
    }

    $.post("update.php", submit_data, function(data) {
        res = $.parseJSON(data);
        
        if (res.debug && typeof(console) !== 'undefined') {
            console.log(res.debug);
        }

        if (!res.success) {
            dialog_error(res.text);
        } else {
            dialog_results(res.text);
        }
        
        set_waiting(false);
    }), 'json';

    return false;
}


function set_waiting(state) {
    var cursor = "auto";
    if (state)
        cursor = "progress";

    $('#dialog').css('cursor', cursor);
    $('.ui-widget-overlay').css('cursor', cursor);
}


function dialog_confirm(text) {
    var d = $('#dialog');
    d.dialog('destroy');
    $('#dialog-text').html(text);

    d.dialog({
        autoOpen: true,
        modal: true,
        dialogClass: '',
        title: 'Confirm',
        resizable: false,
        draggable: false,
        buttons: {
            "Ok": function() { 
                do_confirm_action();
                // $(this).dialog("close"); 
            }, 
            "Cancel": function() { 
                $(this).dialog("close"); 
            } 
        }
    });
}


function dialog_results(text) {
    var d = $('#dialog');
    d.dialog('destroy');
    $('#dialog-text').html(text);

    d.dialog({
        autoOpen: true,
        modal: true,
        dialogClass: '',
        title: 'Success!',
        resizable: false,
        draggable: false,
        closeOnEscape: true,
        buttons: {
            "Close": function() {
                $(this).dialog("close");
            }
        },
        close: function(event, ui) {
            // Trigger page refresh when dialog closed
            location.reload();
        },
    });
}


function dialog_wait(text) {
    var d = $('#dialog');
    d.dialog('close');
    d.dialog('destroy');

    $('#dialog-text').html(text);

    d.dialog({
        autoOpen: true,
        modal: true,
        dialogClass: '',
        title: 'Working...',
        resizable: false,
        draggable: false,
        closeOnEscape: false,
        buttons: {},
        open: function(event, ui) {
            // Hide the 'close' button in the corner
            $(".ui-dialog-titlebar-close").hide();
        },
        close: function(event, ui) { alert('closed! wtf'); },
    });
}

function dialog_error(text) {
    var d = $('#dialog');
    d.dialog('destroy');
    $('#dialog-text').html(text);

    d.dialog({
        autoOpen: true,
        modal: true,
        dialogClass: 'ui-state-error',
        title: 'Error!',
        resizable: false,
        draggable: false,
        buttons: {
            "Ok": function() {
                $(this).dialog("close");
            },
        }
    });
}

