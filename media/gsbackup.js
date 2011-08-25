/*   Will prevent text selection, but also messes up editing text
window.onload = function() {
    var e = document.getElementsByTagName('body')[0];
    e.onselectstart = function() {return false;} // ie
    e.onmousedown = function() {return false;} // mozilla
}
*/

$(document).ready(function(){
    $(".panel-trigger").click(function(){
        $(this).next('.panel').slideToggle("fast");
    });

// on hover, show indicator (up or down arrow depending on state?)

/*
    $(".panel-trigger").hover(
        function(){
            var parent = $(this).parent();
            border = $(parent).css("border");
            $(parent).css("border", "1px solid yellow");
        }, function(){
            $(this).parent().css("border", border);
        }
    );
*/
/*
    $(".panel-trigger").hover(
        function(){
            color = $(this).parent().css("background-color");
            $(this).parent().css("background-color", "yellow");
        }, function(){
            $(this).parent().css("background-color", color);
        }
    );
*/


    // Disable 'submit' button on 'install' page after submitted
    $('#form_install').submit(function(){
        $('input[type=submit]', this).attr('disabled', 'disabled');
    });


    //  Open or close custom s3 flyout when selection is made
    $("select").change(function(){
        if ($(this).val() == "1") {
            $(this).next('.panel').slideDown("fast");
        } else {
            $(this).next('.panel').slideUp("fast");
        }
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

            $(this).closest('form').find("input[name="+name+"]").each(function() {
                $(this).attr("checked", value);
            });
    });

    // Disable 'all' checkbox if individual checkbox is selected
    $("input[type='checkbox'][value!='all']").change(function() {
        var name = $(this).attr("name");
        var all_obj = $(this).closest('form').find("input[name="+name+"][value='all']");

        if (all_obj.attr('checked')) {
            all_obj.attr('checked', false);
        }
    });
    
    // Download button
    $("input.download-button").click(function() {
        window.location.href = "download/" + $(this).attr('name');
    });
    
    // Upload to S3 button
    $("input.s3-upload-button").click(function() {
        var text = "Upload to S3?"
        var id = $(this).attr("backup-id");
        
        submit_data = "type=s3&id=" + id;

        change_popup(text, "submit", true);
        open_popup();
        return false;
    });
    
    // Create backup
    $(".backup-button").click(function() {
        var match = /backup-(\d+)/.exec($(this).attr('id'));
        var name = $(this).attr('name');
        submit_data = "type=backup&id=" + match[1];
        action = "backup";
        
        change_popup("Backup " + name + " now?", "submit", true)
        open_popup();
    });

// Forms => confirmation popup and ajax submit
    
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
            text = '<p class="error">' + text + '</p>';
            change_popup(text, "none", true);
            open_popup();
            return false;
        }

        // Determine text to show in popup
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
        
        change_popup(text, "submit", true);
        open_popup();
        return false;
    });
    
    $("#popup-button-submit").click(function() {
        $.post("update.php", submit_data, function(data) {
            res = $.parseJSON(data);
            var text;
            
            if (res.debug && typeof(console) !== 'undefined') {
                console.log(res.debug);
            }

            if (!res.success) {
                text = "Error: " + res.text;
            } else {
                text = res.text;
            }
            
            set_waiting(false);
            change_popup(text, "close", false);
        }), 'json';
        
        if (action == 'backup')
        {
            change_popup("Please wait...", "none", true);
            set_waiting(true);
            action == '';
        }
        
        return false;
    });
    
    $(".popup-close").click(function() {
        close_popup();
        location.reload();
    });

    $(".popup-cancel").click(function() {
        close_popup();
    });
});

var popup_status = false;
var submit_data = "";
var action = "";

function open_popup() {
    if (popup_status)
        return;
        
    center_popup();
    
    $("#popup-background").css({
        "opacity": "0.7"
    });
    
    $("#popup-background").fadeIn("fast");
    $("#popup").fadeIn("slow");
    popup_status = true;
}

function close_popup() {
    if (!popup_status)
        return;
        
    $("#popup-background").fadeOut("fast");
    $("#popup").fadeOut("fast");
    popup_status = false;
}

function change_popup(text, button, show_cancel) {
    // Set the popup text
    if (text && text != "") {
        $('#popup-text').html(text);
    }

    // Enable submit or close button (or neither)
    $("#popup-button-submit").hide();
    $("#popup-button-close").hide();
    if (button != "none") {
        $("#popup-button-" + button).show();
    }

    // Show or hide cancel button
    if (show_cancel) {
        $("#popup-button-cancel").show();
    } else {
        $("#popup-button-cancel").hide();
    }
}

function center_popup() {
    var w_width = $(window).width();
    var w_height = $(window).height();

    var p_height = $("#popup").height();
    var p_width = $("#popup").width();
    
    var w_scroll_x = $(window).scrollLeft();
    var w_scroll_y = $(window).scrollTop();

    $("#popup").css({
        "position": "absolute",
        "top": (w_height/2 - p_height/2) + w_scroll_y,
        "left": (w_width/2 - p_width/2) + w_scroll_x
    });

    $("#popup-background").css({
        "height": w_height,
        "width": w_width
    });
}

function set_waiting(state) {
    var cursor = "auto";
    if (state)
        cursor = "progress";

    $("#popup").css('cursor', cursor);
    $('#popup-background').css('cursor', cursor);
}
