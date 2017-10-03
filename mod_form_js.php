jQuery("#id_clipurl").after("<span>(Accepts YouTube URL)</span>");
jQuery("#id_playabletimestart").after("<span>(e.g. 0)</span>");
jQuery("#id_playabletimeend").after("<span>(e.g. 120)</span>");
jQuery("#id_videowidth").after("<span>(e.g. 448)</span>");
jQuery("#id_videoheight").after("<span>(e.g. 336)</span>");
    
jQuery('#id_clipselect').change(function() {
    if (jQuery('#id_clipselect').val() === "0") {
        jQuery('#id_submitbutton,#id_submitbutton2').attr('disabled', false);
    } else {
        jQuery('#id_submitbutton,#id_submitbutton2').attr('disabled', true);
    }
});

jQuery('#id_clipurl,#id_playabletimestart,#id_playabletimeend,#id_videowidth,#id_videoheight').change(function() {
    jQuery('#id_submitbutton,#id_submitbutton2').attr('disabled', true);
});

jQuery('#id_preview').click(function() {
    previewSuccess = false;

    jQuery('#id_submitbutton,#id_submitbutton2').attr('disabled', true);
    
    jQuery.ajax({
        type: "POST",
        url: wwwroot + '/mod/videoannotation/database.php', 
        data: {
            "c1_command": "getclipinfo",
            "c1_clipurl": jQuery('#id_clipurl').val(),
        },
        dataType: "json",
        async: false,
        success: function(jsonData, textStatus) {
            if (!jsonData instanceof Array)
                throw "Remote host returned an error message: " + jsonData["message"];
            
            if (jsonData[0]["success"] !== true)
                throw "Remote host returned an error message: " + jsonData[0]["message"];

			if (typeof jsonData[0]['start'] != 'undefined' && jQuery('#id_playabletimestart').val() == '') {
				jQuery('#id_playabletimestart').val(jsonData[0]['start']);
			}

			if (typeof jsonData[0]['width'] != 'undefined' && jQuery('#id_videowidth').val() == '') {
				jQuery('#id_videowidth').val(jsonData[0]['width']);
			}
			
			if (typeof jsonData[0]['height'] != 'undefined' && jQuery('#id_videoheight').val() == '') {
				jQuery('#id_videoheight').val(jsonData[0]['height']);
			}
            
            jwplayerParams = {
                height: Number(jQuery('#id_videoheight').val()),
                width: Number(jQuery('#id_videowidth').val()),
                controls: true,
                autostart: true,
                allowscriptaccess: "always",
                allowfullscreen: "true",
                volume: 66,
                mute: false,
            };

            if(jsonData[0]["file"].match(/youtube/g)) {
              jwplayerParams["file"] = jsonData[0]["file"];
              jwplayerParams["streamer"] = jsonData[0]["streamer"];
            } else {  
              jwplayerParams["file"] = jsonData[0]["streamer"] + "mp4:" + jsonData[0]["file"];
            }
 
            // Create a JWPlayer instance
            // We have to give JWPlayer a unique ID each time 
            // because otherwise the onPlay event won't be called in subsequent previews.
            
            var suffix = new Date().getTime();
            jQuery("#flashPlayerArea1").children().remove();
            jQuery("#flashPlayerArea1").append("<div id='flashPlayerArea2_" + suffix + "'></div>");
            jwplayer("flashPlayerArea2_" + suffix).setup(jwplayerParams);
            
            jwplayer("flashPlayerArea2_" + suffix).onPlay(function(evt) {
                // If it's called the first time, re-enable the save buttons
                
                if (!previewSuccess) {
                    previewSuccess = true;
                    jQuery('#id_submitbutton,#id_submitbutton2').removeAttr('disabled');
                }
                
            });
            
            jwplayer("flashPlayerArea2_" + suffix).onMeta(function(evt) {
                if (typeof evt == "undefined" || typeof evt.metadata == "undefined")
                    return;
                
                // If the meta has the width and the user has not given it, use the meta's width
                
                if (typeof evt.metadata.width != "undefined" && !(Number(jQuery('#id_videowidth').val()) > 0)) {
                    this.resize(evt.metadata.width, player.getHeight());
                    jQuery('#id_videowidth').val(evt.metadata.width);
                }
                
                // If the meta has the height and the user has not given it, use the meta's height
                
                if (typeof evt.metadata.height != "undefined" && !(Number(jQuery('#id_videoheight').val()) > 0)) {
                    this.resize(player.getWidth(), evt.metadata.height);
                    jQuery('#id_videoheight').val(evt.metadata.height);
                }
                
                // If the meta has the duration and (the user has not given it or it value exceeds the meta's duration), use the meta's duration
                
                if (typeof evt.metadata.duration != "undefined") {
                    var duration = Math.round(evt.metadata.duration);
                    if (!(Number(jQuery('#id_playabletimeend').val()) > 0) || Number(jQuery('#id_playabletimeend').val()) > duration)
                        jQuery('#id_playabletimeend').val(duration);
                }
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log(textStatus, errorThrown);
            console.log("error function called in jwplayer");
        }
    });
});

