/* Tag */
//Use this global variable to keep track of event label whenever showMap() is called
var savedNewContent;
var sameTag = undefined;
CSAVTimelineTag = function(params) {
    var requiredFieldNames = ["timeline", "name"];
    for (var idx = 0; idx < requiredFieldNames.length; idx++) {
        if (params[requiredFieldNames[idx]] !== undefined)
            this[requiredFieldNames[idx]] = params[requiredFieldNames[idx]];
        else
            throw "CSAVTimelineTag: Parameter \"" + requiredFieldNames[idx] + "\" is required."
    }
    
    var optionalFieldNames = ["id", "color", "level"];
    for (var idx = 0; idx < optionalFieldNames.length; idx++) {
        this[optionalFieldNames[idx]] = params[optionalFieldNames[idx]];
    }
}

CSAVTimelineTag.prototype.getId = function() {
    return this.id;
}

CSAVTimelineTag.prototype.getTimeline = function() {
    return this.timeline;
}
    
CSAVTimelineTag.prototype.getName = function() {
    return this.name;
}

CSAVTimelineTag.prototype.getColor = function() {
    return this.color;
}

CSAVTimelineTag.prototype.getLevel = function () {
    return this.level;
}

CSAVTimelineTag.prototype.setId = function(id) {
    this.id = id;
}
    
CSAVTimelineTag.prototype.setName = function(name) {
    this.name = name;
}

CSAVTimelineTag.prototype.setColor = function(color) {
    this.color = color;
}

CSAVTimelineTag.prototype.setLevel = function(level) {
    this.level = level;
}

/* Event */

CSAVTimelineEvent = function(params) {
    var requiredFieldNames = ["tag", "startTime", "endTime"];
    for (var idx = 0; idx < requiredFieldNames.length; idx++) {
        if (params[requiredFieldNames[idx]] !== undefined)
            this[requiredFieldNames[idx]] = params[requiredFieldNames[idx]];
        else
            throw "CSAVTimelineEvent: Parameter \"" + requiredFieldNames[idx] + "\" is required."
    }
    
    var optionalFieldNames = ["id", "comment", "latitude", "longitude", "scope","level"];
    for (var idx = 0; idx < optionalFieldNames.length; idx++) {
        this[optionalFieldNames[idx]] = params[optionalFieldNames[idx]];
    }
}

CSAVTimelineEvent.prototype.deepCopy = function() {
    var event = this;
    return new CSAVTimelineEvent({
        id: event.id,
        tag: event.tag,
        startTime: event.startTime,
        endTime: event.endTime,
        comment: event.comment,
        latitude: event.latitude,
        longitude: event.longitude,
        scope: event.scope,
        level: event.level
    });
}

CSAVTimelineEvent.prototype.setId = function(id) {
    this.id = id;
}
    
CSAVTimelineEvent.prototype.getId = function() {
    return this.id;
}
    
CSAVTimelineEvent.prototype.getTag = function() {
    return this.tag;
}
    
CSAVTimelineEvent.prototype.setTag = function(tag) {
    this.tag = tag;
}
    
CSAVTimelineEvent.prototype.setStartTime = function(startTime) {
    this.startTime = startTime
}
    
CSAVTimelineEvent.prototype.getStartTime = function() {
    return this.startTime;
}
    
CSAVTimelineEvent.prototype.setEndTime = function(endTime) {
    this.endTime = endTime;
}
    
CSAVTimelineEvent.prototype.getEndTime = function() {
    return this.endTime;
}
    
CSAVTimelineEvent.prototype.setComment = function(comment) {
    this.comment = comment;
}
    
CSAVTimelineEvent.prototype.getComment = function() {
    return this.comment;
}

CSAVTimelineEvent.prototype.setLat = function(latitude) {
    this.latitude = latitude;
}
    
CSAVTimelineEvent.prototype.getLat = function() {
    return this.latitude;
}

CSAVTimelineEvent.prototype.setLng = function(longitude) {
    this.longitude = longitude;
}
    
CSAVTimelineEvent.prototype.getScope = function() {
    return this.scope;
}
CSAVTimelineEvent.prototype.setScope = function(scope) {
    this.scope = scope;
}
    
CSAVTimelineEvent.prototype.getLng = function() {
    return this.longitude;
}

CSAVTimelineEvent.prototype.getLevel = function () {
    return this.level;
}

CSAVTimelineEvent.prototype.setLevel = function (level) {
    this.level = level;
}

CSAVTimeline = function(params) {
    var requiredFieldNames = ["id",
    "minorMarkerInterval", "majorMarkerInterval", "minTime", "maxTime", "selector", "zoomFactor", "clipId", "readOnly", "streamUpdate", "readOnlyGroup"];
    for (var idx = 0; idx < requiredFieldNames.length; idx++) {
        if (params[requiredFieldNames[idx]] !== undefined)
            this[requiredFieldNames[idx]] = params[requiredFieldNames[idx]];
        else
            throw "CSAVTimeline: Parameter \"" + requiredFieldNames[idx] + "\" is required."
    }
    
    var optionalFieldNames = ["userId", "groupId"];
    for (var idx = 0; idx < optionalFieldNames.length; idx++) {
        this[optionalFieldNames[idx]] = params[optionalFieldNames[idx]];
    }
    
    // Some of the parameters need to be of the Number type
    
    var numberFieldNames = ["minorMarkerInterval", "majorMarkerInterval", "minTime", "maxTime", "zoomFactor", "clipId"];
    for (var idx = 0; idx < numberFieldNames.length; idx++) {
        var num = Number(params[numberFieldNames[idx]]);
        if (!isNaN(num))
            this[numberFieldNames[idx]] = num;
        else
            throw "CSAVTimeline: Parameter \"" + requiredFieldNames[idx] + "\" must be a number."
    }
    
    // Initialize
    
    this.listeners = {};
    this.recordingEvents = {};
    this.tagDialogOpen = {};
    this.eventDialogOpen = {};
    
    var timeline = this;
    
    // Add back tags and events
    
    this.tags = [];
    this.events = [];
    this.fetchDataXHR = undefined;
    this.fetchDataTimestamp = 0;
    
    this.redraw();
    this.fetchData();
    if (typeof this.streamUpdate != "undefined" && this.streamUpdate > 0) {
        this.fetchDataInterval = setInterval(function() {
            if (typeof timeline.fetchDataXHR == "undefined")
                timeline.fetchData();
        }, this.streamUpdate);
    }

    // SSC-1176
    // Adding an array to keep track of the last tag or event adds or deletions.
    this.lastChanges = [];

    // SSC-1191: Detect if the clip has changed
    this.clipModified = undefined;
    
    // SSC-1157
    // Apparently the width of the event bands changes during initialization (don't know why though)
    // so we should redraw with the updated width in mind
    
    delete timeline.noRedraw.marker;
    this.redraw();
}

CSAVTimeline.prototype.fetchData = function() {
    var timeline = this;

    //SSC-1191: Detect changes to the clip
    var clipData = {
        "c1_command": "getclipdata",
        "c1_clipid": this.clipId,
        "c1_timeout": 0
    }

    this.fetchDataXHR = false;
    this.fetchDataXHR = jQuery.ajax({
        type: "POST",
        url: "database.php",
        data: clipData,
        dataType: "json",
        async: true,
        success: function(jsonData, textStatus) {
            if (!jsonData instanceof Array)
                throw "CSAVTimelineTag.prototype.save: Remote host returned an error message: " + jsonData["message"];
            
            if (typeof jsonData == "undefined" || jsonData == null)
                throw "CSAVTimelineTag.prototype.save: jsonData not defined";
            
            if (typeof jsonData[0] == "undefined")
                throw "CSAVTimelineTag.prototype.save: jsonData[0] not defined";
            
            if (jsonData[0]["success"] !== true)
                throw "CSAVTimelineTag.prototype.save: Remote host returned an error message: " + jsonData[0]["message"];
            
            var timemodified = jsonData[0]["data"][0].timemodified;
            if (timeline.clipModified == undefined) {
                timeline.clipModified = timemodified;
                return;
            }
            if (timeline.clipModified !== timemodified) {
                timeline.sendMessage(timeline, "clipChanged", timemodified);
                timeline.clipModified = timemodified;
            }
        },

        complete: function(jqXHR, textStatus) {
            timeline.fetchDataXHR = undefined;
        }
    });
    
    var data = {
        "c1_command": "gettagsevents",
        "c1_clipid": this.clipId,
        "c1_timestamp": this.fetchDataTimestamp,
        "c1_timeout": 0
    }
    if (this.userId) data["c1_userid"] = this.userId;
    if (this.groupId) data["c1_groupid"] = this.groupId;
    
    var tagids = '';
    for (var idx in this.tags) {
        if (tagids != '')
            tagids += ',';
        tagids += this.tags[idx].getId();
    }
    data["c1_tags"] = tagids;
    
    var eventids = '';
    for (var idx in this.events) {
        if (eventids != '')
            eventids += ',';
        eventids += this.events[idx].getId();
    }
    data["c1_events"] = eventids;
    
    this.fetchDataXHR = false;
    this.fetchDataXHR = jQuery.ajax({
        type: "POST",
        url: "database.php", 
        data: data,
        dataType: "json",
        async: true,
        success: function(jsonData, textStatus) {
            if (!jsonData instanceof Array)
                throw "CSAVTimelineTag.prototype.save: Remote host returned an error message: " + jsonData["message"];
            
            if (typeof jsonData == "undefined" || jsonData == null)
                throw "CSAVTimelineTag.prototype.save: jsonData not defined";
            
            if (typeof jsonData[0] == "undefined")
                throw "CSAVTimelineTag.prototype.save: jsonData[0] not defined";
            
            if (jsonData[0]["success"] !== true)
                throw "CSAVTimelineTag.prototype.save: Remote host returned an error message: " + jsonData[0]["message"];
            
            timeline.fetchDataTimestamp = jsonData[0]["timestamp"];
            
            var changed = false;
            
            for (var idx in jsonData[0]["tags"]) {
                var tagData = jsonData[0]["tags"][idx];
                var tagObj = timeline.findTag(Number(tagData.id));
                if (tagObj)
                    timeline.editTag(Number(tagData["id"]), undefined, undefined, tagData["name"], tagData["color"], false, Number(tagData["level"]));
                else 
                    timeline.addTag(Number(tagData["id"]), tagData["name"], tagData["color"], false, Number(tagData["level"]));
                changed = true;
            }
            
            for (var idx in jsonData[0]["events"]) {
                var eventData = jsonData[0]["events"][idx];
                var eventObj = timeline.findEvent(Number(eventData.id));
                if (eventObj)
                {
                    if (eventData["latitude"] && eventData["longitude"])
                    {
                        timeline.editEvent(Number(eventData["id"]), undefined, undefined, undefined, Number(eventData["starttime"]), Number(eventData["endtime"]), eventData["content"], false, Number(eventData["latitude"]), Number(eventData["longitude"]), eventData["scope"], Number(eventData["level"]));
                    }
                    else
                    {
                        timeline.editEvent(Number(eventData["id"]), undefined, undefined, undefined, Number(eventData["starttime"]), Number(eventData["endtime"]), eventData["content"], false, undefined, undefined, undefined, Number(eventData["level"]));
                    }
                }

                else
                {
                    if (eventData["latitude"] && eventData["longitude"])
                    {
                        timeline.addEvent(Number(eventData["id"]), Number(eventData["tagid"]), Number(eventData["starttime"]), Number(eventData["endtime"]), eventData["content"], false, Number(eventData["latitude"]), Number(eventData["longitude"]), eventData["scope"], Number(eventData["level"]));
                    }
                    else
                    {
                        timeline.addEvent(Number(eventData["id"]), Number(eventData["tagid"]), Number(eventData["starttime"]), Number(eventData["endtime"]), eventData["content"], false, undefined, undefined, undefined, Number(eventData["level"]));
                    }
                }
                changed = true;
            }
            
            for (var idx in jsonData[0]["deletedevents"]) {
                var eventId = jsonData[0]["deletedevents"][idx];
                var tagId = timeline.findEvent(Number(eventId)).getTag().getId();
                timeline.removeEvent(eventId, false);
                if (!(typeof timeline.editEventDialogOpen === "undefined"))
                     delete timeline.editEventDialogOpen[tagId];
                jQuery('#EventBar_Event' + eventId + '_Timeline' + timeline.id).remove();
                changed = true;
            }

            for (var idx in jsonData[0]["deletedtags"]) {
                var tagId = jsonData[0]["deletedtags"][idx];
                timeline.removeTag(tagId, false);
                if (!(typeof timeline.editTagDialogOpen === "undefined")) 
                    delete timeline.editTagDialogOpen[tagId];
                jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + timeline.id).remove();
                changed = true;
            }
            
            if (changed)
                timeline.redraw(true);
            
            if (typeof jsonData[0]["onlineusers"] != "undefined")
                timeline.sendMessage(timeline, "onlineUsersUpdated", jsonData[0]["onlineusers"]);
        },
        
        complete: function(jqXHR, textStatus) {
            timeline.fetchDataXHR = undefined;
        }
    });
    
}

CSAVTimeline.prototype.handleError = function(err) {
    if (typeof err.errortype != "undefined") {
        switch (err.errortype) {
            case "writeconflict":
                alert("FinishGroupModeASAPErrorMessage: " + err.message);
                break;
            default:
                alert("error: " + err.message);
        }
    } else {
        alert("error: " + err.message);
    }
}

CSAVTimeline.prototype.inArray = function(needle, haystack, comparator) {
    for (var key in haystack) {
        if (comparator(needle, haystack[key]))
            return key;
    }
    return undefined;
}

CSAVTimeline.prototype.redraw = function(forceRedraw) {
    var timeline = this;

    // Create the timeline base if it doesn't exist

	if (typeof this.noRedraw === 'undefined' || !this.noRedraw) {
		jQuery('#TimelineBase_Timeline' + this.id).remove();
		
        var str = '';
        str += '<div id="TimelineBase_Timeline' + this.id + '" class="TimelineBase">';
        str += "    <div id='LeftMarker_Timeline" + this.id + "' class='LeftMarker'></div>";
        str += "    <div id='RightMarker_Timeline" + this.id + "' class='RightMarker'></div>";
        str += "    <div id='CurrentTimeBar_Timeline" + this.id + "' class='CurrentTimeBar'></div>";
        str += '    <div id="TimeMarkerDigitPanel_Timeline' + this.id + '" class="TimeMarkerDigitPanel">';
        str += '        <table border=0 width="100%">';
        str += '            <tr valign="top">';
        str += '                <td id="TimeMarkerDigitPanel1_Timeline' + this.id + '" class="TimeMarkerDigitPanel1">';
        str += '                    <button id="AddTagButton_Timeline' + this.id + '" class="AddTagButton" type="submit" title="Click to add a tag">Add Tag</button>';
        str += '                    <button id="BulkModeOnButton_Timeline' + this.id + '" class="BulkModeOnButton" title="Enable selecting multiple tags">Bulk Mode On</button>';
        str += '                    <button id="BulkModeOffButton_Timeline' + this.id + '" class="BulkModeOffButton" title="Disable selecting multiple tags">Bulk Mode Off</button>';
        str += '<br>';
        str += '                    <a href="#" id="SelectAllButton_Timeline' + this.id + '" class="SelectAllButton" title="Select all tags">all</a>';
        str += '                    <a href="#" id="SelectNoneButton_Timeline' + this.id + '" class="SelectNoneButton" title="Unselect all tags">none</a>';
        str += '                    <a href="#" id="BulkStartButton_Timeline' + this.id + '" class="BulkStartButton" title="Start recording for the selected tags">start</a>';
        str += '                    <a href="#" id="BulkStopButton_Timeline' + this.id + '" class="BulkStopButton" title="Stop recording for the selected tags">stop</a>';
        str += '                </td>';
        str += '                <td id="TimeMarkerDigitPanel2_Timeline' + this.id + '" class="TimeMarkerDigitPanel2">';
        str += '                </td>';
        str += '            </tr>';
        str += '        </table>';
        str += '    </div>';
        str += '    <div id="AddTagBand_Timeline' + this.id + '" class="AddTagBand">';
        str += '        <input id="ZoomInButton_Timeline' + this.id + '" class="ZoomInButton" type="image" border=0 src="images/zoomin.png" title="Zoom in"/>';
        str += '        <input id="ZoomOutButton_Timeline' + this.id + '" class="ZoomOutButton" type="image" border=0 src="images/zoomout.png" title="Zoom out"/>';
        str += '    </div>';
        str += "    <div id='CurrentTimeBarHandle_Timeline" + this.id + "' class='CurrentTimeBarHandle'></div>";
        str += '    <div id="TimeMarkerPanel_Timeline' + this.id + '" class="TimeMarkerPanel"></div>';
        str += '</div>';        
        jQuery(this.selector).append(str);
jQuery('#TimelineBase_Timeline' + this.id).append('<div id="test"></div>');
        
        // Bulk Mode On button turns bulk mode on
        
        jQuery('#BulkModeOnButton_Timeline' + this.id).click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            timeline.bulkMode = true;
            timeline.redraw();
            return false;
        });
        
        // Bulk Mode Off button turns bulk mode off
        
        jQuery('#BulkModeOffButton_Timeline' + this.id).click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            timeline.bulkMode = false;
            timeline.redraw();
            return false;
        });
        
        // Select All button checks all checkboxes
        
        jQuery('#SelectAllButton_Timeline' + this.id).click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            timeline.selectTags(timeline.tags);
            timeline.redraw();
            return false;
        });
        
        // Select None button unchecks all checkboxes
        
        jQuery('#SelectNoneButton_Timeline' + this.id).click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            timeline.selectTags([]);
            timeline.redraw();
            return false;
        });
        
        // Bulk Start button starts recording for all tags
        
        jQuery('#BulkStartButton_Timeline' + this.id).click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (timeline.readOnly) {
                if(timeline.readOnlyGroup) {
                    alert("You cannot edit another group's annotation.");
                } else {
                    alert("The annotation can not be changed after it has been submitted.");
                }
                return false;
            }
            
            try {
                timeline.startRecording(timeline.selectedTags);
                timeline.redraw();
            } catch (err) {
                timeline.handleError(err);
            }
            return false;
        });
        
        // Bulk Stop button stops recording for all tags
        
        jQuery('#BulkStopButton_Timeline' + this.id).click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (timeline.readOnly) {
                if(timeline.readOnlyGroup) {
                    alert("You cannot edit another group's annotation.");
                } else {
                    alert("The annotation can not be changed after it has been submitted.");
                }
                return false;
            }
            
            try {
                timeline.stopRecording(timeline.selectedTags);
                timeline.redraw();
            } catch (err) {
                timeline.handleError(err);
            }
            return false;
        });
        
        // We use TimeMarkerDigitPanel2's width (which should be set to every event band's width)
        // as the initial value of eventBandWidth
        // If TimeMarkerDigitPanel2 is ever resized, update the value
        
        jQuery('#TimeMarkerDigitPanel2_Timeline' + this.id).resize(function() {
            delete timeline.noRedraw.marker;
            this.redraw();
        });
        
        // TimeMarkerDigitPanel2 (the area with the time numbers), when clicked, skips the video to another time
        
        jQuery('#TimeMarkerDigitPanel2_Timeline' + this.id).click(function(clickEvent) {
            clickEvent.preventDefault();
            clickEvent.stopPropagation();
            
            var pixel = clickEvent.pageX - jQuery('#TimeMarkerPanel_Timeline' + timeline.id).offset().left;
            var time = timeline.pixelToSecond(pixel);
            
            // SSC-978: Ignore clicks that result in attempted seeks outside the playable range
            // We need this check because the event band is longer than the "clickable" range
            
            if (time < timeline.minTime || time > timeline.maxTime)
                return;
            
            timeline.setCurrentTime(time, true);
			delete timeline.noRedraw.currentTime;
            timeline.redraw();
        });
        
        // Add Tag button brings up the Add Tag dialog
        
        jQuery('#AddTagButton_Timeline' + this.id).click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (timeline.readOnly) {
                if(timeline.readOnlyGroup) {
                    alert("You cannot edit another group's annotation.");
                } else {
                    alert("The annotation can not be changed after it has been submitted.");
                }
                return false;
            }
            
            timeline.addTagDialogOpen = true;
            timeline.redraw();
        });
        
        // Zoom In button increases the zoom factor by 2; the maximum zoom factor is 256
        
        jQuery('#ZoomInButton_Timeline' + this.id).click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (timeline.zoomFactor * 2 <= 256)
                timeline.zoomFactor *= 2;
			delete timeline.noRedraw.marker;
            timeline.redraw();
            return;
        });
        
        // Zoom Out button decreases the zoom factor by 1/2; the minimum zoom factor is 1
        
        jQuery('#ZoomOutButton_Timeline' + this.id).click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (timeline.zoomFactor / 2 >= 1)
                timeline.zoomFactor /= 2;
			delete timeline.noRedraw.marker;
            timeline.redraw();
            return;
        });
        
        // Dragging the current time bar handle moves the current time
        
        jQuery('#CurrentTimeBarHandle_Timeline' + this.id).draggable({
            axis: 'x',
            cursor: 'w-resize',
            containment: [
                jQuery('#TimeMarkerPanel_Timeline' + this.id).offset().left,
                0,
                jQuery('#TimeMarkerPanel_Timeline' + this.id).offset().left + jQuery('#TimeMarkerPanel_Timeline' + this.id).width() - jQuery('#CurrentTimeBarHandle_Timeline' + this.id).width(),
                0
            ],
			
			start: function() {
				timeline.sendMessage(timeline, 'currentTimeDragStart', timeline.getCurrentTime());
			},

            drag: function(event, ui) {
                var newTime = timeline.pixelToSecond(ui.position.left - jQuery('#TimeMarkerPanel_Timeline' + timeline.id).position().left);
                timeline.setCurrentTime(newTime, true);
				delete timeline.noRedraw.currentTime;
                timeline.redraw();
            },
			
			stop: function() {
				timeline.sendMessage(timeline, 'currentTimeDragStop', timeline.getCurrentTime());
			}
        });
        
        // Make bands sortable
        
        jQuery('#TimelineBase_Timeline' + this.id).sortable({
            disabled: false,
            axis: 'y',
            forcePlaceholderSize: true,
            items: '.TagEventBand',
            update: function(updateEvent, ui) {
                if (timeline.readOnly)
                    return;
                
                var elementIds = jQuery(this).sortable("toArray");
                var tagIds = [];
                for (var idx in elementIds) {
                    a = elementIds[idx];
                    var tagId = jQuery('#' + elementIds[idx]).data('tagId');
                    if (tagId !== undefined)
                        tagIds.push(tagId);
                }

                // Update the database via AJAX

                var error = undefined;
                jQuery.ajax({
                    type: "POST",
                    url: "database.php", 
                    data: {
                        "c1_command": "reordertags", 
                        "c1_clipid": timeline.clipId,
                        "c1_groupid": timeline.groupId, 
                        "c1_orders": tagIds.join(",")
                    }, 
                    dataType: "json",
                    async: false,
                    success: function(jsonData, textStatus) {
                        if (!jsonData instanceof Array) {
                            error = new Error();
                            error.message = "error when receiving data from server";
                            return;
                        }

                        if (jsonData[0]["success"] !== true) {
                            error = new Error();
                            if (typeof jsonData[0]["errortype"] != "undefined")
                                error.errortype = jsonData[0]["errortype"];
                            if (typeof jsonData[0]["message"] != "undefined")
                                error.message = jsonData[0]["message"];
                            return;
                        }
                    }
                });
                if (typeof error != "undefined")
                    throw error;

                // Reorder our tags array

                var newTags = [];
                for (var idx in tagIds) {
                    newTags.push(timeline.findTag(tagIds[idx]));
                }

                timeline.tags = newTags;
            }
        });
		
		this.noRedraw = {};
    }
	
	// Create the time markers if it doesn't exist
	
	if (typeof this.noRedraw.marker === 'undefined' || !this.noRedraw.marker) {
		jQuery('#TimeMarkerPanel_Timeline' + this.id).children().remove();
		var eventBandWidth = jQuery('#TimeMarkerDigitPanel2_Timeline' + this.id).width();
		
        var minTime;
        var maxTime;
        
        // Find the smallest value in {1s, 10s, 100s, 1000s, ...} for majorMarkerInterval
        // so that the space between two major markers is at least 20 pixels
        
        for (var majorMarkerInterval = 1; majorMarkerInterval < this.maxTime - this.minTime; majorMarkerInterval *= 10) {
            // Find max(time value of the left edge of the event band, min playable time)
            // Find min(time value of the right edge of the event band, max playable time)
            // Round both the the nearest (majorMarkerInterval)
            
            minTime = Math.floor(Math.max(this.pixelToSecond(0), this.minTime) / majorMarkerInterval) * majorMarkerInterval;
            maxTime = Math.ceil(Math.min(this.pixelToSecond(eventBandWidth), this.maxTime) / majorMarkerInterval) * majorMarkerInterval;
            
            // If the space is at least 20 pixel, use this majorMarkerInterval value
            
            if (this.secondToPixel(majorMarkerInterval, true) >= 20)
                break;
        }

		var str = '';
        for (var tInSec = minTime; tInSec <= maxTime; tInSec += majorMarkerInterval) {
            str += "<div class='TimeAxisMajorMarker' style='left: " + this.secondToPixel(tInSec) + "px;'></div>";
            str += "<div class='TimeAxisMajorMarkerText' style='left: " + this.secondToPixel(tInSec) + "px;'>" + tInSec + "</div>";
        }
		
		jQuery('#TimeMarkerPanel_Timeline' + this.id).append(str);
		
		this.noRedraw.marker = true;
	}
	
    
    // If the current time is set, show CurrentTimeBarHandle and CurrentTimeBar at that left position
    // Otherwise, hiden them
    
	if (typeof this.noRedraw.currentTime === 'undefined' || !this.noRedraw.currentTime) {
            timeline.setTimelineMarker();
	}
    
    //
    
    if (typeof this.bulkMode !== "undefined" && this.bulkMode) {
        jQuery('#BulkModeOnButton_Timeline' + this.id).hide();
        jQuery('#BulkModeOffButton_Timeline' + this.id).show();
        jQuery('#SelectAllButton_Timeline' + this.id).show();
        jQuery('#SelectNoneButton_Timeline' + this.id).show();
        jQuery('#BulkStartButton_Timeline' + this.id).show();
        jQuery('#BulkStopButton_Timeline' + this.id).show();
    } else {
        jQuery('#BulkModeOnButton_Timeline' + this.id).show();
        jQuery('#BulkModeOffButton_Timeline' + this.id).hide();
        jQuery('#SelectAllButton_Timeline' + this.id).hide();
        jQuery('#SelectNoneButton_Timeline' + this.id).hide();
        jQuery('#BulkStartButton_Timeline' + this.id).hide();
        jQuery('#BulkStopButton_Timeline' + this.id).hide();
    }
    
    // Process each tag
    
    for (var idx in this.tags) {
        var tagId = this.tags[idx].getId();
    
        // Create the tag band and event band if they're not there
        
        if (jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + this.id).length == 0) {
            var str = '';
            str += '<div id="TagEventBand_Tag' + tagId + '_Timeline' + this.id + '" class="TagEventBand" style="">';
            str += '    <div id="TagBand_Tag' + tagId + '_Timeline' + this.id + '" class="TagBand" title="Double-click to edit this tag; drag to reorder this tag">';
            str += '    </div>';
            str += '    <div id="EventBand_Tag' + tagId + '_Timeline' + this.id + '" class="EventBand" title="Double-click to add an event">';
            str += '    </div>';
            str += '</div>';
            
            // Add the new tag event band to after the last existing tag event band
            // Or, if no tag event band exists, add after the time marker digit panel
            
            var lastTagEventBand = jQuery('#TimelineBase_Timeline' + timeline.id).find('.TagEventBand').last();
            if (lastTagEventBand.length > 0) {
                lastTagEventBand.after(str);
            } else {
                jQuery('#TimeMarkerDigitPanel_Timeline' + this.id).after(str);
            }
            
            // Set the tag ID so that sortable can use it to update the order
            
            jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId).data('oldlevel', this.findTag(tagId).getLevel());
            jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);

            // Set the initial height of the EventBand_Tag and TagEventBand_Tag.
            console.log("setting initial height");
            var EventBandTag = jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id);
            var level = this.findTag(tagId).getLevel();
            var baseHeight = 22; 
            EventBandTag.css('height', level * baseHeight);
            EventBandTag.parent().css('height', level * baseHeight);
 
            // If the event band is clicked, go to the time corresponding to the position clicked
            
            jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id).click(function(clickEvent) {
                clickEvent.preventDefault();
                //clickEvent.stopPropagation();
                
                var pixel = clickEvent.pageX - jQuery('#TimeMarkerPanel_Timeline' + timeline.id).offset().left;
                var time = timeline.pixelToSecond(pixel);
                
                // SSC-978: Ignore clicks that result in attempted seeks outside the playable range
                // We need this check because the event band is longer than the "clickable" range
                
                if (time < timeline.minTime || time > timeline.maxTime)
                    return;
                
                timeline.setCurrentTime(time, true);
				delete timeline.noRedraw.currentTime;
                timeline.setTimelineMarker();
            });
            
            // If the event band is double-clicked, bring up the Add Event dialog
            
            jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id).click(function(clickEvent) {
                if(event.shiftKey) {
                    clickEvent.preventDefault();
                    clickEvent.stopPropagation();

                    if (timeline.readOnly) {
                        if(timeline.readOnlyGroup) {
                            alert("You cannot edit another group's annotation.");
                        } else {
                            alert("The annotation can not be changed after it has been submitted.");
                        }  
                        return;
                    }
                
                    if (jwplayer().getState() == "PLAYING")
                        jwplayer().pause();
                
                    // SSC-978: Ignore clicks that result in attempted seeks outside the playable range
                    // We need this check because the event band is longer than the "clickable" range
                
                    var pixel = clickEvent.pageX - jQuery('#TimeMarkerPanel_Timeline' + timeline.id).offset().left;
                    var time = timeline.pixelToSecond(pixel);
                    if (time < timeline.minTime || time > timeline.maxTime)
                        return;
                
                    var tagId = jQuery(this).data('tagId');
                    if (typeof timeline.editEventDialogOpen === "undefined") 
                        timeline.editEventDialogOpen = {};
                    timeline.editEventDialogOpen[tagId] = 0;
                    timeline.setTimelineMarker();
                    timeline.redraw();
                }
            });
        }
        
        // If Edit Tag dialog requested, create it if it doesn't exist
        
        if (typeof this.editTagDialogOpen !== "undefined" 
        && typeof this.editTagDialogOpen[tagId] !== "undefined" 
        && this.editTagDialogOpen[tagId]
        && !this.readOnly) {
            if (jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).length == 0) {
                var str = '';
                str += '<table id="EditTagDialog_Tag' + tagId + '_Timeline' + this.id + '" class="EditTagDialog">';
                str += '    <tr>';
                str += '        <th colspan=2 id="EditTagDialogTitle_Tag' + tagId + '_Timeline' + this.id + '" class="EditTagDialogTitle">Edit Tag</th>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td>Name:</td>';
                str += '        <td><textarea id="EditTagDialogName_Tag' + tagId + '_Timeline' + this.id + '" class="EditTagDialogName" name="name" cols="14"></textarea></td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td>Color:</td>';
                str += '        <td>';
                str += '            <input type="hidden" id="EditTagDialogColor_Tag' + tagId + '_Timeline' + this.id + '" class="EditTagDialogColor" name="color" value="" />';
                str += '            <input id="ColorPicker' + tagId + '_Edit" class="ColorPicker"  value=' + timeline.findTag(tagId).getColor() + '></input>';

                str += '            <div style="clear: both;"></div>';
                str += '        </td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td colspan=2>';
                str += '            <div id="EditTagDialogError_Tag' + tagId + '_Timeline' + this.id + '" class="EditTagDialogError"></div>';
                str += '        </td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td colspan=2>';
                str += '            <input id="EditTagDialogSaveButton_Tag' + tagId + '_Timeline' + this.id + '" class="EditTagDialogSaveButton" type="submit" value="Save" />';
                str += '            <input id="EditTagDialogCancelButton_Tag' + tagId + '_Timeline' + this.id + '" class="EditTagDialogCancelButton" type="submit" value="Cancel" />';
                str += '            <input id="EditTagDialogDeleteButton_Tag' + tagId + '_Timeline' + this.id + '" class="EditTagDialogDeleteButton" type="submit" value="Delete This Tag" />';
                str += '            <input id="EditTagDialogOldName_Tag' + tagId + '_Timeline' + this.id + '" type="hidden" />';
                str += '            <input id="EditTagDialogOldColor_Tag' + tagId + '_Timeline' + this.id + '" type="hidden" />';
                str += '        </td>';
                str += '    </tr>';
                str += '</table>';
                jQuery('#TagBand_Tag' + tagId + '_Timeline' + this.id).append(str);

                // SSC-978:
                // Prevent clicks on the dialog from being passed through
                
                jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.stopPropagation();
                });
                
                /********* Color Picker Code ********************/
                
                //bind the new color dialogs
                jscolor.init();
 
                //set the initial color and tag information 
                var color = '#' + document.getElementById('ColorPicker' + tagId + '_Edit').color.toString();
                jQuery('#EditTagDialogColor_Tag' + tagId + '_Timeline' + timeline.id).val(color);
                jQuery('#ColorPicker' + tagId + '_Edit').data('tagId', tagId);
                                
                //change the tag color when a new color is selected
                jQuery('#ColorPicker' + tagId + '_Edit').change( function() {

                    tagId = jQuery(this).data('tagId');
                    var color = '#' + this.color.toString();
                    jQuery('#EditTagDialogColor_Tag' + tagId + '_Timeline' + timeline.id).val(color);

                });

                /******** End Color Picker Code *****************/

                // Record existing name and color
                
                jQuery('#EditTagDialogOldName_Tag' + tagId + '_Timeline' + this.id).val(timeline.findTag(tagId).getName());
                jQuery('#EditTagDialogOldColor_Tag' + tagId + '_Timeline' + this.id).val(timeline.findTag(tagId).getColor());
                
                // Show existing name
                
                jQuery('#EditTagDialogName_Tag' + tagId + '_Timeline' + this.id).val(timeline.findTag(tagId).getName());
                
                
                
                
                jQuery('#EditTagDialogSaveButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId).data('timeline', timeline);
                jQuery('#EditTagDialogCancelButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                jQuery('#EditTagDialogDeleteButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                
                // If Save button clicked,
                // update the tag and hide tag dialog and show tag controls
                
                jQuery('#EditTagDialogSaveButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    var tagId = jQuery(event.target).data('tagId');
                    var oldName = jQuery('#EditTagDialogOldName_Tag' + tagId + '_Timeline' + timeline.id).val();
                    var oldColor = jQuery('#EditTagDialogOldColor_Tag' + tagId + '_Timeline' + timeline.id).val();
                    var newName = jQuery('#EditTagDialogName_Tag' + tagId + '_Timeline' + timeline.id).val();
                    if (typeof newName != 'string' || newName.trim() == '') {
                        jQuery('#EditTagDialogError_Tag' + tagId + '_Timeline' + timeline.id).text('Name cannot be empty.');
                        timeline.redraw();
                        return;
                    }
                    if (typeof timeline.findTag(newName) != 'undefined' && timeline.findTag(newName).getId() != tagId) {
                        jQuery('#EditTagDialogError_Tag' + tagId + '_Timeline' + timeline.id).text('A tag with the same name already exists.');
                        timeline.redraw();
                        return;
                    }
                    var newColor = jQuery('#EditTagDialogColor_Tag' + tagId + '_Timeline' + timeline.id).val();
                    try {
                        timeline.editTag(tagId, oldName, oldColor, newName, newColor, true, timeline.findTag(tagId).getLevel());
                        delete timeline.editTagDialogOpen[tagId];
                        savedNewContent = undefined;
                        timeline.redraw();
                    } catch (err) {
                        timeline.handleError(err);
                    }
                });
                
                // If Cancel button clicked,
                // hide tag dialog and show tag controls
                
                jQuery('#EditTagDialogCancelButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    var tagId = jQuery(event.target).data('tagId');
                    delete timeline.editTagDialogOpen[tagId];
                    timeline.redraw();
                });
                
                // If Delete button clicked
                // delete the tag and remove the tag event band
                
                jQuery('#EditTagDialogDeleteButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    

                    var tagId = jQuery(event.target).data('tagId');
                    var tagName = timeline.findTag(tagId).name;

                    //SSC-1176 adding a confirm dialog to all tag deletions
                    var str = "Are you sure you want to delete the tag \"" + tagName + "\"?"
                    if (timeline.groupId)
                        str += " Remember you are in group mode and removing the tag may affect other users.";
                    if (!confirm(str))
                        return;

                    timeline.removeTag(tagId, true);
                    delete timeline.editTagDialogOpen[tagId];
                    jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + timeline.id).remove();
                    timeline.redraw();
                });
            }

            var eventband = jQuery('#EditEventDialogContent_Tag'+ tagId + '_Timeline' + timeline.id).height();
            jQuery('#TagControls_Tag' + tagId + '_Timeline' + this.id).hide();
            jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).show();
            var dialogHeight = jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).height();  
            jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).parent().height(dialogHeight); 
            //If there is an editTag dialog opening when there is an EditEvent Dialog open (meaning the eventBand is already larger than it would be just opening an editTag band) dont change the size. Otherwise there is only an editTag opening and you should change the size
            if (jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).parent().parent().height() < dialogHeight) {
                jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).parent().parent().height(dialogHeight);
            }

            // When the textarea is expanded and an editTagDialog is opened, make sure the TagEventBand remains the
            // same size (editTagDialog < expanded textarea).
            if (eventband == 150) {
                jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + timeline.id).height(eventband+50);
            } else { 
                jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + timeline.id).height(dialogHeight);
            }

        // If tag dialog not requested, show the tag name, start/stop buttons, checkbox (if bulk mode is on).
        } else {
            if (jQuery('#TagControls_Tag' + tagId + '_Timeline' + this.id).length == 0) {
                var str = '';
                str += '<div id="TagControls_Tag' + tagId + '_Timeline' + this.id + '" class="TagControls"></div>';
                str += '<input type="checkbox" id="TagCheckbox_Tag' + tagId + '_Timeline' + this.id + '" class="TagCheckbox" />';
                str += '<div id="TagLabel_Tag' + tagId + '_Timeline' + this.id + '" class="TagLabel"></div>';
                str += '<button id="TagStartButton_Tag' + tagId + '_Timeline' + this.id + '" class="TagStartButton" href="#" title="Click to start tagging an event">Start</button>';
                str += '<button id="TagStopButton_Tag' + tagId + '_Timeline' + this.id + '" class="TagStopButton" href="#" title="Click to stop tagging an event">Stop</button>';
                jQuery('#TagBand_Tag' + tagId + '_Timeline' + this.id).append(str);
                
                jQuery('#TagBand_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                jQuery('#TagControls_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                jQuery('#TagCheckbox_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                jQuery('#TagStartButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                jQuery('#TagStopButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                jQuery('#TagLabel_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId); 
                
                // SSC-987: TagCheckbox instances need to intecept the click event, or the underneath TagBand 
                // will get it, and this will somehow prevent the clicked TagCheckbox from becoming checked
                
                jQuery('#TagCheckbox_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.stopPropagation();
                });
                
                jQuery('#TagCheckbox_Tag' + tagId + '_Timeline' + this.id).change(function(event) {
                    event.stopPropagation();
                    
                    // If in bulk mode, select only all tags that are checked
                    // (i.e. tags not checked are unselected) and redraw
                    
                    if (timeline.bulkMode) {
                        var tags = [];
                        jQuery('#TimelineBase_Timeline' + timeline.id).find('.TagCheckbox').each(function() {
                            if (jQuery(this).attr('checked'))
                                tags.push(jQuery(this).data('tagId'));
                        });
                        timeline.selectTags(tags);
                        timeline.redraw();
                    }
                });
                
                jQuery('#TagStartButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    if (timeline.readOnly) {
                        if(timeline.readOnlyGroup) {
                            alert("You cannot edit another group's annotation.");
                        } else {
                            alert("The annotation can not be changed after it has been submitted.");
                        }
                        return false;
                    }                    
                    var tagId = jQuery(event.target).data('tagId');
                    try {
                        timeline.startRecording([tagId]);
                        timeline.redraw();
                    } catch (err) {
                        timeline.handleError(err);
                    }
                });
                
                jQuery('#TagStopButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    if (timeline.readOnly) {
                        if(timeline.readOnlyGroup) {
                            alert("You cannot edit another group's annotation.");
                        } else {
                            alert("The annotation can not be changed after it has been submitted.");
                        }
                        return;
                    }    
                    var tagId = jQuery(event.target).data('tagId');
                    
                    try {
                        timeline.stopRecording([tagId]);
                        timeline.redraw();
                    } catch (err) {
                        timeline.handleError(err);
                    }
                });
                
                jQuery('#TagBand_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    // If not in bulk mode, select only this tag
                    // (i.e. tags not clicked are unselected) and redraw
                    
                    if (!timeline.bulkMode) {
                        timeline.selectTag(jQuery(event.target).data('tagId'));
                    }
                    
                    // Redraw
                    
                    timeline.redraw();
                });
                
                jQuery('#TagBand_Tag' + tagId + '_Timeline' + this.id).dblclick(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    if (timeline.readOnly) {
                        if(timeline.readOnlyGroup) {
                            alert("You cannot edit another group's annotation."); 
                        } else {
                            alert("The annotation can not be changed after it has been submitted.");
                        }
                        return;
                    }
                    
                    if (typeof timeline.editTagDialogOpen === "undefined")
                        timeline.editTagDialogOpen = {};
                    timeline.editTagDialogOpen[jQuery(event.target).data('tagId')] = true;
                    timeline.redraw();
                });
            }
            
            // Remove any existing Edit Tag dialog
            // Show the tag controls
           
            var level = this.findTag(tagId).getLevel();
            var baseHeight = 22; 
            
            var dialogHeight = jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).height();
            var editDialog = jQuery('#EditEventDialog_Tag' + tagId + '_Timeline' + this.id);
            jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).parent().height('');
            jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).parent().parent().css('height', baseHeight * level);
            jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id).remove();
            jQuery('#TagControls_Tag' + tagId + '_Timeline' + this.id).show();
            //If there is an editEventDialog open, and you are closing and editTagDialog, make sure the eventBand stays large enough
            if(editDialog.height() !== null || editDialog.height() !== undefined) {
                jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + timeline.id).height(parseInt(editDialog.height(), 10) + parseInt(editDialog.css("top"), 10));
            }
            // If bulk mode is on, show the tag checkboxes
            // Otherwise, hide the tag checkboxes
            
            if (this.bulkMode)
                jQuery('#TimelineBase_Timeline' + this.id).find('.TagCheckbox').show();
            else
                jQuery('#TimelineBase_Timeline' + this.id).find('.TagCheckbox').hide();
            
            // If the tag is recording, hide the start button and show the stop button
            // otherwise, show the start button and hide the stop button
            
            if (typeof this.recordingEvents[tagId] !== "undefined" && this.recordingEvents[tagId]) {
                jQuery('#TagStartButton_Tag' + tagId + '_Timeline' + this.id).hide();
                jQuery('#TagStopButton_Tag' + tagId + '_Timeline' + this.id).show();
            } else {
                jQuery('#TagStartButton_Tag' + tagId + '_Timeline' + this.id).show();
                jQuery('#TagStopButton_Tag' + tagId + '_Timeline' + this.id).hide();
            }
            
            // Set the tag's text
            
            jQuery('#TagLabel_Tag' + tagId + '_Timeline' + this.id).text(this.tags[idx].getName());
            
            // Set the tag's background color and check/uncheck the tag checkbox
            // depending on whether the tag is selected
            
            var tagSelected = false;
            for (var idx in this.selectedTags) {
                if (this.selectedTags[idx].getId() == tagId) {
                    tagSelected = true;
                    break;
                }
            }
            
            // Set the tag label's background color to the tag's color or (if the color not defined) the default color.
            var tagColor = this.findTag(tagId).getColor();
            if (tagSelected) {
                //jQuery('#TagLabel_Tag' + tagId + '_Timeline' + this.id).css('border-color', '');
                jQuery('#TagBand_Tag' + tagId + '_Timeline' + this.id)
                .addClass('TagBandSelected')
                .css('background-color', tagColor)
                .css('border-color', '');
            } else {
                jQuery('#TagBand_Tag' + tagId + '_Timeline' + this.id)
                .removeClass('TagBandSelected')
                .css('background-color', tagColor)
                .css('border-color', tagColor);
            }
            // Set the text color to conrast the background color
            var textColor = "#ffffff";
            var tagColorDec = parseInt("0x" + tagColor.substr(1));
            if (tagColorDec > 0xffffff/2)
                textColor = "#000000";
            jQuery('#TagLabel_Tag' + tagId + '_Timeline' + this.id).css('color', textColor);
          

            jQuery('#TagCheckbox_Tag' + tagId + '_Timeline' + this.id).attr('checked', tagSelected);
        }
    }
    
    // Add the add tag band
    
    if (typeof this.addTagDialogOpen !== "undefined" && this.addTagDialogOpen && !this.readOnly) {
        // If Add Tag dialog does not exist,
        // create and initialize it
        
        if (jQuery('#AddTagDialog_Timeline' + this.id).length == 0) {
            // Create the Add Tag dialog
            
            var str = '';
            str += '<table id="AddTagDialog_Timeline' + this.id + '" class="AddTagDialog">';
            str += '    <tr>';
            str += '        <th colspan=2 id="AddTagDialogTitle_Timeline' + this.id + '" class="AddTagDialogTitle">Add Tag</th>';
            str += '    </tr>';
            str += '    <tr>';
            str += '        <td>Name:</td>';
            str += '        <td><textarea id="AddTagDialogName_Timeline' + this.id + '" class="AddTagDialogName" name="name"></textarea></td>';
            str += '    </tr>';
            str += '    <tr>';
            str += '        <td>Color:</td>';
            str += '        <td>';
            str += '            <input type="hidden" id="AddTagDialogColor_Timeline' + this.id + '" class="AddTagDialogColor" name="color" value="" />';
            str += '            <input id="ColorPicker' + tagId + '_Add" class="ColorPicker"  value= "#808080"></input>';

            str += '            <div style="clear: both;"></div>';
            str += '        </td>';
            str += '    </tr>';
            str += '    <tr>';
            str += '        <td colspan=2>';
            str += '            <div id="AddTagDialogError_Timeline' + this.id + '" class="AddTagDialogError"></div>';
            str += '        </td>';
            str += '    </tr>';
            str += '    <tr>';
            str += '        <td colspan=2>';
            str += '            <input id="AddTagDialogSaveButton_Timeline' + this.id + '" class="AddTagDialogSaveButton" type="submit" value="Save" />';
            str += '            <input id="AddTagDialogCancelButton_Timeline' + this.id + '" class="AddTagDialogCancelButton" type="submit" value="Cancel" />';
            str += '        </td>';
            str += '    </tr>';
            str += '</table>';
            jQuery('#AddTagBand_Timeline' + this.id).append(str);
            
            // SSC-978:
            // Prevent clicks on the dialog from being passed through
            
            
            jQuery('#AddTagDialog_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                event.stopPropagation();
            });
           

            /********* Color Picker Code ********************/
            
            //bind the color elements
            jscolor.init();
              
            //set the initial color and tag information 
            var color = '#808080';
            jQuery('#AddTagDialogColor_Timeline' + timeline.id).val(color);
            jQuery('#ColorPicker' + tagId + '_Add').data('tagId', tagId);
                             
            //change the tag color when a new color is selected
            jQuery('#ColorPicker' + tagId + '_Add').change( function() {
                var color = '#' + this.color.toString();
                jQuery('#AddTagDialogColor_Timeline' + timeline.id).val(color);

            });

            /******** End Color Picker Code *****************/           
            
            // If add button clicked,
            // use the name given in the dialog to add the tag
            // and hide tag dialog and show tag controls
            
            jQuery('#AddTagDialogSaveButton_Timeline' + this.id).click(function(event) {
                event.preventDefault();
                event.stopPropagation();
                
                var name = jQuery('#AddTagDialogName_Timeline' + timeline.id).val();
                if (typeof name != 'string' || name.trim() == '') {
                    jQuery('#AddTagDialogError_Timeline' + timeline.id).text('Name cannot be empty.');
                    timeline.redraw();
                    return;
                }
                if (typeof timeline.findTag(name) != 'undefined') {
                    jQuery('#AddTagDialogError_Timeline' + timeline.id).text('A tag with the same name already exists.');
                    timeline.redraw();
                    return;
                }
                
                var color = jQuery('#AddTagDialogColor_Timeline' + timeline.id).val();
                
                try {
                    timeline.addTag(timeline.randomId(), name, color, true, 1);
                    delete timeline.addTagDialogOpen;
                    timeline.redraw();
                } catch (err) {
                    timeline.handleError(err);
                }
            });
            
            // If cancel button clicked,
            // hide tag dialog and show tag controls
        
            jQuery('#AddTagDialogCancelButton_Timeline' + this.id).click(function(event) {
                event.preventDefault();
                event.stopPropagation();
                
                var tagId = jQuery(event.target).data('tagId');
                delete timeline.addTagDialogOpen;
                timeline.redraw();
            });
        }
        
        // Show Add Tag dialog and hide Add Tag button
        
        jQuery('#AddTagDialog_Timeline' + this.id).show();
        jQuery('#AddTagButton_Timeline' + this.id).hide();
    } else {
        // Hide Add Tag dialog and show Add Tag button
        
        jQuery('#AddTagDialog_Timeline' + this.id).remove();
        jQuery('#AddTagButton_Timeline' + this.id).show();
    }
    
    // Event bands
    for (var tagIdx in this.tags) {
        var tagId = this.tags[tagIdx].getId();

        // If Edit Event dialog requested for this tag, open it.
        if (typeof this.editEventDialogOpen !== "undefined" &&
                typeof this.editEventDialogOpen[tagId] !== "undefined" &&
                !this.readOnly) {
            var eventId = this.editEventDialogOpen[tagId];
            // Check if the map has been requested.
            var drawMap = false;
            if ( typeof this.editEventDialogMapOpen !== "undefined" &&
                    typeof this.editEventDialogMapOpen[tagId] !== "undefined" ) {
                drawMap = true;
            }
            console.log("drawMap", drawMap, "tagId", tagId);
            console.log("eventId", eventId);
            
            // If Edit Event dialog does not exist, create it. If the dialog does exist, and sameTag is defined, you are switching the editDialog to another event in the same tag.
            if (jQuery('#EditEventDialog_Tag' + tagId + '_Timeline' + this.id).length == 0 || (sameTag !== undefined && timeline.findEvent(sameTag).getTag().getId() == tagId)) {
                // Find the previous and next events by passing by reference into sortEvent(), which is also used elsewhere.
                
                var eventsForThisTag = {list: []};
                var prevEventId = {id : undefined};
                var nextEventId = {id : undefined};
                timeline.sortEvent(eventsForThisTag, prevEventId, nextEventId, eventId, tagId); 
                // Create dialog
                
                var str = '';
                str += '<table id="EditEventDialog_Tag' + tagId + '_Timeline' + this.id + '" class="EditEventDialog" title="Modify event; move to another event" style="z-index: 1000">';
                str += '    <tr valign="top">';
                str += '        <td style="padding: 5px 5px 5px 5px; width: 1%; white-space: nowrap; font-weight: bold">Label event:</td>';
                str += '        <td style="padding: 5px 0 5px 0;"><textarea id="EditEventDialogContent_Tag' + tagId + '_Timeline' + this.id + '" class="EditEventDialogContent" name="content" title="Double click here to enlarge textbox"></textarea><input id="EditEventDialogOldContent_Tag' + tagId + '_Timeline' + this.id + '" type="hidden" /></td>';
                str += '        <td style="padding: 5px 0 5px 5px;" width="1%">';
                str += '            <input id="EditEventDialogSaveButton_Tag' + tagId + '_Timeline' + this.id + '" class="EditEventDialogSaveButton" type="submit" value="OK" title="Save changes and close the dialog" />';
                str += '        </td>';
                str += '        <td style="padding: 5px 0 5px 0;" width="1%">';
                str += '            <input id="EditEventDialogCancelButton_Tag' + tagId + '_Timeline' + this.id + '" class="EditEventDialogCancelButton" type="submit" value="Cancel" title="Discard changes and close the dialog" />';
                str += '        </td>';
                str += '        <td style="padding: 5px 0 5px 0;" width="1%">';
                str += '            <input id="EditEventDialogDeleteButton_Tag' + tagId + '_Timeline' + this.id + '" class="EditEventDialogDeleteButton" type="submit" value="Delete" title="Delete this event and close the dialog" />';
                str += '        </td>';
                if (prevEventId.id !== undefined) {
                    str += '        <td style="padding: 5px 0 5px 0;" width="1%">';
                    str += '            <input id="EditEventDialogPreviousButton_Tag' + tagId + '_Timeline' + this.id + '" class="EditEventDialogPreviousButton" type="submit" value="Previous" title="Save changes and go to the previous event of the same tag"/>';
                    str += '        </td>';
                }
                if (nextEventId.id !== undefined) {
                    str += '        <td style="padding: 5px 5px 5px 0;" width="1%">';
                    str += '            <input id="EditEventDialogNextButton_Tag' + tagId + '_Timeline' + this.id + '" class="EditEventDialogNextButton" type="submit" value="Next" title="Save changes and go to the next event of the same tag" />';
                    str += '        </td>';
                }
                str += '    </tr>';
                
                /******* geotagging ********/ 
                str += '    <tr>';
                str += '        <td style="font-weight: bold; padding: 5px 5px 5px 5px; width: 1%; white-space: nowrap">Geotagging Data: </td>';
                str += '        <td>';
                str += '            <label>Latitude</label><input id="lat' + tagId +'" type="textbox">';
                str += '            <label>Longitude</label><input id="lng' + tagId + '" type="textbox">';
                str += '            <input id="geocodeLatLng' + tagId + '" type="submit" value="Reverse Geocode">';
                str += '        </td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td></td><td colspan=5>';
                str += '            <label>Address</label><input id="address' + tagId + '" type="textbox" style="width: 200px">';
                str += '            <input id="geocodeAddress' + tagId + '" type="submit" value="Geocode">';
                str += '            <label>Scope</label><select id="scope' + tagId + '"></select>';
                str += '            <input id="showMap' + tagId + '" type="submit" value="Show Map">';
                str += '        </td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td colspan="100%">';
                str += '            <div id="map_canvas'+tagId+'" style="width: 600px; height: 400px;"></div>';
                str += '    </tr>';
                /******* end geotagging ****/

                str += '</table>';
                if(sameTag !== undefined) {
		    if (sameTag > 0 && timeline.findEvent(sameTag).getTag().getId() > 0) {
			var eventObj = timeline.findEvent(sameTag);
			var oldContent = jQuery('#EditEventDialogOldContent_Tag' + eventObj.getTag().getId() + '_Timeline' + timeline.id).val();
			var newContent = jQuery('#EditEventDialogContent_Tag' + eventObj.getTag().getId() + '_Timeline' + timeline.id).val();
			timeline.editEvent(eventObj, undefined, undefined, oldContent, undefined, undefined, newContent, true);
                    } 
                    jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id).empty();
                }
                jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id).append(str);
                var clicks = 0;
                var tag = tagId;
                  
                jQuery('#EditEventDialog_Tag' + tagId + '_Timeline' + timeline.id).click(function() {
                    if(event.shiftKey) {
                        event.stopPropagation();
                    }
                });
                jQuery('#EditEventDialogContent_Tag'+ tagId + '_Timeline' + timeline.id).click(function(e) {
                    if(e.shiftKey) 
                    {
                        e.stopPropagation();
                        e.preventDefault();
                        if(clicks == 0 || clicks%2 == 0) {
                            $('#TagEventBand_Tag' + tag + '_Timeline' + timeline.id).height('250px');
                            $(this).height('150px');
                            $('#EventBand_Tag' + tag + '_Timeline' + timeline.id).height('250px');
                            clicks++;
                        } else {
                            if($('#EditTagDialog_Tag' + tag + '_Timeline' + timeline.id).height() !== null) {
                                $('#TagEventBand_Tag' + tag + '_Timeline' + timeline.id).height($('#EditTagDialog_Tag' + tag + '_Timeline' + timeline.id).height());
                            } else {
                                $('#TagEventBand_Tag' + tag + '_Timeline' + timeline.id).height('150px');
                            }
                            $(this).height('40px');
                            clicks++;
                        }
                    }   
                });
                /***** geotagging ****/
                initializeGeotag("map_canvas"+tagId);
                console.log(tagId);
                var latId = 'lat'+tagId;
                var lngId = 'lng'+tagId;
                var addressId = 'address'+tagId;
                var scopeId = 'scope'+tagId;
                var canvasId = 'map_canvas'+tagId;
                var tid = tagId;
                var eid = eventId;

                //display the map if it has been requested
                console.log("display map ", drawMap);
                if ( drawMap == true)
                {
                    console.log("drawing map");
                    jQuery('#map_canvas' + tagId).css("display", "block")
                    jQuery('#showMap' + tagId).val("Hide Map");
                    
                    //make tag bands temporarily unsortable to prevent bugs with the map
                    jQuery('#TimelineBase_Timeline' + timeline.id).sortable({
                        disabled: true
                    });
                }
                else
                {
                    console.log("hiding map");
                    jQuery('#map_canvas' + tagId).css("display", "none")
                    jQuery('#showMap' + tagId).val("Show Map");

                    //reenable sortable tag bands
                    jQuery('#TimelineBase_Timeline' + timeline.id).sortable({
                        disabled: false 
                    });
                }
                function showMap() {
                    var eventObj = timeline.findEvent(eid);
                    if (jwplayer().getState() == "PLAYING") {
                        jwplayer().pause();
                    }
                    if (eventObj !== null && eventObj !== undefined) {
                        eventObj.setLat(jQuery('#lat'+tid).val());
                        eventObj.setLng(jQuery('#lng'+tid).val());
                        eventObj.setScope(jQuery('#scope'+tid+' option:selected').val());
                    }
                    // If the label has been changed but not saved, save the changed text so when the event is removed
                    // and then redrawn, the changed text is set in the label box rather than the previously saved text
                    // (basically prevents label from always resetting when showing map.
                    savedNewContent = jQuery('#EditEventDialogContent_Tag' + tid + '_Timeline' + timeline.id).val();
			        if ( typeof timeline.editEventDialogMapOpen == "undefined" ||
			                typeof  timeline.editEventDialogMapOpen[tid] == "undefined") {
                        timeline.editEventDialogMapOpen = {};
                        timeline.editEventDialogMapOpen[tid] = true;
			        } else {
                        delete timeline.editEventDialogMapOpen[tid];
                        delete timeline.editEventDialogMapOpen;
			        }
			        console.log("map",  typeof timeline.editEventDialogMapOpen);

			        // Ensure the timeline gets redrawn.

                    // Remove the dialog to ensure it gets redrawn.
                    var level = timeline.findTag(tagId).getLevel();
                    var baseHeight = 22;

                    var editEventDialog = jQuery('#EditEventDialog_Tag' + tid + '_Timeline' + timeline.id);
                    editEventDialog.parent().parent().css('height', level * baseHeight);
                    editEventDialog.parent().css('height', level * baseHeight);
                    editEventDialog.remove();
                    jQuery('#EventBand_Tag' + tid + '_Timeline' + timeline.id).find('.EventBar').show();
                    timeline.redraw();
                }

                // Add functionality to geocode buttons.

                jQuery('#geocodeLatLng' + tagId).click(function(event) {
                    codeLocation('latlng', latId, lngId, addressId, scopeId, eid, 0);
                    showMap();
                });
                jQuery('#geocodeAddress' + tagId).click(function(event) {
                    codeLocation('address', latId, lngId, addressId, scopeId, eid, 0);
                    showMap();
                });
                jQuery('#showMap' + tagId).click(function(event) {
                    console.log("TagId=%d", tagId);
                    console.log("tid=%d", tid);
                    console.log("eventid=%d", eventId);
                    console.log("eid=%d", eid);
                    showMap();
                });

                /***** end geotagging ****/
                
                // SSC-978:
                // Prevent clicks on the dialog from being passed through
                
                jQuery('#EditEventDialog_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.stopPropagation();
                });
                
                // Fill the tag selection box
                // One <option> element for each tag in the timeline
                // The value is the tag's ID; the text is the tag's name
                // Also, select the option that matches the current tag by ID
                
                var str = '';
                for (var tagIdx2 in this.tags) {
                    str += '<option value="' + this.tags[tagIdx2].getId() + '"></option>';
                }
                jQuery('#EditEventDialogTag_Tag' + tagId + '_Timeline' + this.id).append(str);
                jQuery('#EditEventDialogTag_Tag' + tagId + '_Timeline' + this.id).find('option').each(function(indexInArray, valueOfElement) {
                    jQuery(this).text(timeline.tags[indexInArray].getName());
                });
                jQuery('#EditEventDialogTag_Tag' + tagId + '_Timeline' + this.id).find('option[value=' + tagId + ']').attr('selected', true);
                                
                // Initialize the dialog's elements depending on whether it is a Add Event or a Edit event dialog
                // They have different titles
                // Add Event dialog does not have the "Delete This Event" button
                // Edit Event dialog records event ID in addition to tag ID in the buttons, and set existing values in the textboxes
                
                if (eventId > 0) {
                    var eventObj = timeline.findEvent(eventId);
                    
                    jQuery('#EditEventDialogTitle_Tag' + tagId + '_Timeline' + this.id).text('Edit Event');
                    
                    jQuery('#EditEventDialogOldContent_Tag' + tagId + '_Timeline' + this.id).val(eventObj.getComment());
                    if(savedNewContent !== undefined && sameTag === undefined ) {
                        jQuery('#EditEventDialogContent_Tag' + tagId + '_Timeline' + this.id).val(savedNewContent);
                        savedNewContent = undefined;
                    } else {
                        jQuery('#EditEventDialogContent_Tag' + tagId + '_Timeline' + this.id).val(eventObj.getComment());
                    }
 
                            //geotag data
                    if( isNaN(eventObj.getLat()) && isNaN(eventObj.getLng()) )
                    {
                        jQuery('#lat' + tagId).val(""); 
                        jQuery('#lng' + tagId).val("");
                    }
                    else
                    {
                        jQuery('#lat' + tagId).val(eventObj.getLat()); 
                        jQuery('#lng' + tagId).val(eventObj.getLng());
                    }

                    console.log("initLat=%d initLng=%d", eventObj.getLat(), eventObj.getLng());
                   
                    jQuery('#EditEventDialogSaveButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId).data('eventId', eventId);
                    jQuery('#EditEventDialogCancelButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId).data('eventId', eventId);
                    jQuery('#EditEventDialogDeleteButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId).data('eventId', eventId).data('timeline', this);
                    jQuery('#EditEventDialogPreviousButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId).data('eventId', eventId);
                    jQuery('#EditEventDialogNextButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId).data('eventId', eventId);

                } else {
                    jQuery('#EditEventDialogTitle_Tag' + tagId + '_Timeline' + this.id).text('Add Event');
                    if(newContent != undefined) {
                        jQuery('#EditEventDialogContent_Tag' + tagId + '_Timeline' + this.id).val(newContent);
                    } 
                    jQuery('#EditEventDialogDeleteButton_Tag' + tagId + '_Timeline' + this.id).remove();
                    
                    jQuery('#EditEventDialogSaveButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                    jQuery('#EditEventDialogCancelButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                    jQuery('#EditEventDialogDeleteButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId);
                }

                //Reverse geocode the lat and lng to set the map and address
                if ( jQuery('#lat' + tagId).val() != "" &&
                    jQuery('#lat' + tagId).val() != 0 &&
                    jQuery('#lng' + tagId).val() != "" &&
                    jQuery('#lng' + tagId).val() != 0 )
                {
                    codeLocation('latlng', latId, lngId, addressId, scopeId, eid, 0);
                    if ( typeof eventObj.getScope() != "undefined" ) {

console.log("scope=%s", eventObj.getScope());
                        jQuery('#scope' + tagId + ' option[value="'+eventObj.getScope()+'"]').attr('selected', 'selected');
                    }
                }
                else
                {
                    jQuery('#scope' + tagId).removeAttr('options')
                    jQuery('#scope' + tagId).val('[Empty]');
                }

                // If Save button clicked,
                // update or add the tag, and hide tag dialog and show tag controls
                
                jQuery('#EditEventDialogSaveButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    var eventId = jQuery(event.target).data('eventId');
                    var tagId = jQuery(event.target).data('tagId');
                    try {
                        if (eventId > 0) {
                            var eventObj = timeline.findEvent(eventId);
                            var oldContent = jQuery('#EditEventDialogOldContent_Tag' + tagId + '_Timeline' + timeline.id).val();
                            var newContent2 = jQuery('#EditEventDialogContent_Tag' + tagId + '_Timeline' + timeline.id).val();
                            
                            //geotag data
                            if(jQuery('#lat' + tagId).val() != "" && jQuery('#lat' + tagId).val() != "")
                            {
                                var lat = jQuery('#lat' + tagId).val(); 
                                var lng = jQuery('#lng' + tagId).val();
                                var scope = jQuery('#scope' + tagId + ' option:selected').val();
                                console.log("scope=%s", scope);
                                timeline.editEvent(eventObj, undefined, undefined, oldContent, undefined, undefined, newContent2, true, lat, lng, scope);
                            }
                            else
                            {
                                timeline.editEvent(eventObj, undefined, undefined, oldContent, undefined, undefined, newContent2, true, undefined, undefined, undefined);
                            }

                        } else {
                            var newEventId = timeline.randomId();
                            var startTime = timeline.getCurrentTime();
                            var content = jQuery('#EditEventDialogContent_Tag' + tagId + '_Timeline' + timeline.id).val();
                            if(jQuery('#lat' + tagId).val() != "" && jQuery('#lat' + tagId).val() != "") {
                                var lat = jQuery('#lat' + tagId).val(); 
                                var lng = jQuery('#lng' + tagId).val();
                                var scope = jQuery('#scope' + tagId + ' option:selected').val();
                                timeline.addEvent(newEventId, tagId, startTime, startTime + 1, content, true, lat, lng, scope, this.findEvent(eventId).getLevel());
                            } else {
                                timeline.addEvent(newEventId, tagId, startTime, startTime + 1, content, true, undefined, undefined, undefined, this.findEvent(eventId).getLevel());
                            }
                        }  
                        delete timeline.editEventDialogOpen[tagId];
                        delete timeline.noRedraw.currentTime;
                        
                        //close the map
                        if ( typeof timeline.editEventDialogMapOpen != "undefined" &&
                        typeof timeline.editEventDialogMapOpen[tid] != "undefined" )
                        {
                            delete timeline.editEventDialogMapOpen[tid];
                            delete timeline.editEventDialogMapOpen;
                        }
                        //ensure tag bands are sortable
                        jQuery('#TimelineBase_Timeline' + timeline.id).sortable({
                            disabled: false 
                        });
                        newContent = undefined;
                        timeline.redraw();
                    } catch (err) {
                        timeline.handleError(err);
                    }
                });
                
                // If Cancel button clicked, hide tag dialog and show tag controls.
                jQuery('#EditEventDialogCancelButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var tagId = jQuery(event.target).data('tagId');
                    delete timeline.editEventDialogOpen[tagId];
                    delete timeline.noRedraw.currentTime;
                    // Close the map.
                    if ( typeof timeline.editEventDialogMapOpen != "undefined" &&
                            typeof timeline.editEventDialogMapOpen[tid] != "undefined" ) {
                        delete timeline.editEventDialogMapOpen[tid];
                        delete timeline.editEventDialogMapOpen;
                    }
                    // Ensure tag bands are sortable.
                    jQuery('#TimelineBase_Timeline' + timeline.id).sortable({
                        disabled: false 
                    });

                    savedNewContent = undefined;
                    timeline.redraw();
                });
                
                // If Delete button clicked, delete the tag and remove the tag event band.
                jQuery('#EditEventDialogDeleteButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var timeline = jQuery(this).data('timeline');
                    var tagId = jQuery(event.target).data('tagId');
                    var eventId = jQuery(event.target).data('eventId');
                    // Adjust the videoannotation_tags level and reorder event levels.
                    // Assume that all events will be kept in sequential, increasing order with no gaps between levels,
                    // i.e. 1,3,5 cannot be a possible level scheme.
                    var newLevel = 1;
                    var largestLevel = timeline.findTag(tagId).getLevel(); 
                    var eventLevel = timeline.findEvent(eventId).getLevel();
                    var numEvents = 1;

                    // Find number of events at the level to be deleted.
                    for (events in timeline.events) {
                        if (timeline.events[events].getTag().getId() == tagId 
                                && timeline.events[events].getId() !== eventId
                                && timeline.events[events].getLevel() == eventLevel ) {
                            numEvents = 2;
                            break;
                        }
                    }

                    // Deleting event with largest level.
                    if (largestLevel == eventLevel) {
                        // Case where events aren't on level 1 and multiple events don't exists on a level.
                        // If events on level 1 or multiple events exist on a level, do nothing.
                        if (largestLevel != 1 && numEvents <= 1) {
                            newLevel = largestLevel - 1;
                            var tagObj = timeline.findTag(tagId);
                            timeline.editTag(tagId, tagObj.getName(), tagObj.getColor(), tagObj.getName(), tagObj.getColor(), true, newLevel);
                        }

                    // Deleting event either in the beginning (smallest level) or in the middle levels.
                    } else if (numEvents < 2) {
                        newLevel = largestLevel - 1;
                        for (events in timeline.events) {
                            var cur_event = timeline.events[events];
                            if (cur_event.getTag().getId() == tagId && cur_event.getId() !== eventId) {
                                console.log("cur event level: \n", cur_event.getLevel());
                                if (cur_event.getLevel() > eventLevel) {
                                    console.log("new cur_event level: \n", Number(cur_event.getLevel())-1);
                                    timeline.events[events] = timeline.editEvent(cur_event, cur_event.getStartTime(),
                                                        cur_event.getEndTime(), cur_event.getComment(), undefined, undefined,
                                                        undefined, true, cur_event.getLat(), cur_event.getLng(),
                                                        cur_event.getScope(), Number(cur_event.getLevel())-1);
                                }
                            }
                        }
                        newLevel = largestLevel - 1;
                        var tagObj = timeline.findTag(tagId);
                        timeline.editTag(tagId, tagObj.getName(), tagObj.getColor(), tagObj.getName(), tagObj.getColor(), true, newLevel); 
                    }
                    
                    // Remove event.
                    timeline.removeEvent(eventId, true);
                    jQuery('#EventBar_Event' + eventId + '_Timeline' + timeline.id).remove();
                    delete timeline.editEventDialogOpen[tagId];
                    delete timeline.noRedraw.currentTime;
                    // Close the map.
                    if ( typeof timeline.editEventDialogMapOpen != "undefined" &&
                            typeof timeline.editEventDialogMapOpen[tid] != "undefined" ) {
                        delete timeline.editEventDialogMapOpen[tid];
                        delete timeline.editEventDialogMapOpen;
                    }
                    // Ensure tag bands are sortable.
                    jQuery('#TimelineBase_Timeline' + timeline.id).sortable({
                        disabled: false 
                    });

                    savedNewContent = undefined;
                    timeline.redraw();
                });
                
                // If Previous button clicked, save current event, delete the dialog, set the event ID and redraw.
                jQuery('#EditEventDialogPreviousButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var eventsForThisTag = {list: []};
                    var prevEventId = {id : undefined};
                    var nextEventId = {id : undefined};
                  
                    var eventId = jQuery(event.target).data('eventId');
                    var tagId = jQuery(event.target).data('tagId');
                    
                    try {
                        if (eventId > 0 && tagId > 0) {
                            var eventObj = timeline.findEvent(eventId);
                            var oldContent = jQuery('#EditEventDialogOldContent_Tag' + tagId + '_Timeline' + timeline.id).val();
                            var newContent2 = jQuery('#EditEventDialogContent_Tag' + tagId + '_Timeline' + timeline.id).val();
                            var lat = jQuery('#lat' + tagId).val(); 
                            var lng = jQuery('#lng' + tagId).val();
                            var scope = jQuery('#scope' + tagId + ' option:selected').val();
                            timeline.editEvent(eventObj, undefined, undefined, oldContent, undefined, undefined, newContent2, true, lat, lng, scope);
                        }
                        timeline.sortEvent(eventsForThisTag, prevEventId, nextEventId, eventId, tagId);
                        timeline.editEventDialogOpen[tagId] = prevEventId.id;
                        delete timeline.noRedraw.currentTime;
                        jQuery('#EditEventDialog_Tag' + tagId + '_Timeline' + timeline.id).remove();
                        timeline.selectEvent(timeline.findEvent(prevEventId.id));
                        timeline.redraw();
                    } catch (err) {
                        timeline.handleError(err);
                    }
                });
                
                // If Next button clicked, save current event, delete the dialog, set the event ID and redraw.
                jQuery('#EditEventDialogNextButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var eventsForThisTag = {list: []};
                    var prevEventId = {id : undefined};
                    var nextEventId = {id : undefined};
                 
                    var eventId = jQuery(event.target).data('eventId');
                    var tagId = jQuery(event.target).data('tagId');
                    
                    try {
                        if (eventId > 0 && tagId > 0) {
                            var eventObj = timeline.findEvent(eventId);
                            var oldContent = jQuery('#EditEventDialogOldContent_Tag' + tagId + '_Timeline' + timeline.id).val();
                            var newContent2 = jQuery('#EditEventDialogContent_Tag' + tagId + '_Timeline' + timeline.id).val();
                            var lat = jQuery('#lat' + tagId).val(); 
                            var lng = jQuery('#lng' + tagId).val();
                            var scope = jQuery('#scope' + tagId + ' option:selected').val();
                            timeline.editEvent(eventObj, undefined, undefined, oldContent, undefined, undefined, newContent2, true, lat, lng, scope);
                        }
                        timeline.sortEvent(eventsForThisTag, prevEventId, nextEventId, eventId, tagId); 
                        timeline.editEventDialogOpen[tagId] = nextEventId.id;
                        delete timeline.noRedraw.currentTime;
                        jQuery('#EditEventDialog_Tag' + tagId + '_Timeline' + timeline.id).remove();
                        timeline.selectEvent(timeline.findEvent(nextEventId.id));
                        timeline.redraw();
                    } catch (err) {
                        timeline.handleError(err);
                    }
                });
                
                // Show the dialog.

                var eventBandHeight = jQuery('#TimeMarkerDigitPanel_Timeline' + this.id).height();
                var editEventDialog = jQuery('#EditEventDialog_Tag' + tagId + '_Timeline' + this.id);

                var level = this.findEvent(eventId).getLevel();
                var tagLevel = this.findTag(tagId).getLevel();
                var baseHeight = 22; 
                var editDialogTop = level * baseHeight;
                editEventDialog.css('top', editDialogTop);
                editEventDialog.parent().css('height', editDialogTop + editEventDialog.height());
                editEventDialog.parent().parent().css('height', editDialogTop + editEventDialog.height());
 
                // When an editEventDialog is opening, check to see if there is an editTagDialog open. If one is, make
                // sure the TagEventBand is big enough for the editTagDialog (which is > editEventDialog).
                var editTag = jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + timeline.id)
                if(editTag.height() !== null || editTag.height() !== undefined) {
                    jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + timeline.id).height(parseInt(editTag.height(),10));
                }
            }
        } else {
            // Remove the dialog

            var editEventDialog = jQuery('#EditEventDialog_Tag' + tagId + '_Timeline' + this.id);
            var baseHeight = 22; 
            var level = this.findTag(tagId).getLevel();
            editEventDialog.parent().parent().css('height', baseHeight * level);
            editEventDialog.parent().css('height', baseHeight * level);
            editEventDialog.remove();
            jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id).find('.EventBar').show();
            //If an editTag dialog is opened, then when the editEventDialog is closing, make sure the event band stays large enough for the editTag
            var editTag = jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + timeline.id);
            if(editTag.height() !== null || editTag.height() !== undefined) {
                jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + timeline.id).height(parseInt(editTag.height(), 10));
            }
        } 

        // View Event dialog requested.
        if (typeof this.viewEventDialogOpen !== "undefined" &&
                typeof this.viewEventDialogOpen[tagId] !== "undefined" &&
                this.readOnly) {
            var eventId = this.viewEventDialogOpen[tagId];
            var eventObj = this.findEvent(eventId);

            if (jQuery('#ViewEventDialog_Tag' + tagId + '_Timeline' + this.id).length == 0 ||
                    (sameTag !== undefined && timeline.findEvent(sameTag).getTag().getId() == tagId)) {
                var eventsForThisTag = {list: []};
                var prevEventId = {id : undefined};
                var nextEventId = {id : undefined};
                timeline.sortEvent(eventsForThisTag, prevEventId, nextEventId, eventId, tagId);                
                var str = '';

                str += '<table id="ViewEventDialog_Tag' + tagId + '_Timeline' + this.id + '" class="EditEventDialog">';
                str += '    <tr valign="top">';
                str += '        <td width="50%" style="padding: 5px; white-space: nowrap; font-weight: bold; text-align: right">Event Summary</td>';
                str += '        <td width="50%" style="padding-top: 5px; padding-bottom: 10px; padding-right: 5px">';
                str += '            <input id="ViewEventDialogCloseButton_Tag' + tagId + '_Timeline' + this.id +'" type="button" value="Close" style="float: right"/>';
                if (nextEventId.id !== undefined) {
                    str += '        <input id="ViewEventDialogNextButton_Tag' + tagId + '_Timeline' + this.id + '" type="button" value="Next" style="float:right"/>';
                }
                if (prevEventId.id !== undefined) {
                    str += '        <input id="ViewEventDialogPreviousButton_Tag' + tagId + '_Timeline' + this.id + '" type="button" value="Previous" style="float:right"/>';
                }
                str += '        </td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td style="padding-left: 5px; text-align:center">Event Label: </td>';
                str += '        <td id="view_label' + tagId +'" style="padding-left: 5px"> N/A </td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td style="padding-left: 5px; text-align:center"> Latitude: </td>';
                str += '        <td id="view_lat' + tagId + '"style="padding-left: 5px"></td>';
                str += '        <td> <input id="oldview_lat' + tagId +'" type="hidden"></td>';
                str += '    <tr>';
                str += '        <td style="padding-left: 5px; text-align:center"> Longitude: </td>';
                str += '        <td id="view_lng' + tagId + '" style="padding-left: 5px"></td>';
                str += '        <td> <input id="oldview_lng' + tagId +'" type="hidden"></td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td style="padding-left: 5px; text-align:center"> Address: </td>';
                str += '        <td id="view_address' + tagId + '"style="padding-left: 5px"> </td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td colspan=2 style="text-align:center">';
                str += '            <label>Scope</label><select id="view_scope' + tagId + '"></select>';
                str += '            <input id="view_showMap' + tagId + '" type="submit" value="Show Map">';
                str += '        </td>';
                str += '    </tr>';
                str += '    <tr>';
                str += '        <td colspan=2>';
                str += '            <div id="view_map_canvas'+tagId+'" style="margin: auto; width: 90%; height: 350px; display: none;"></div>';
                str += '        </td>';
                str += '    </tr>';
                str += '</table>';

                if(sameTag !== undefined) {
                    jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id).empty();
                }
                jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id).append(str);
                jQuery('#ViewEventDialog_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    if (event.shiftKey)
                        event.stopPropagation();
                });
                var showMapButton = jQuery('#view_showMap' + tagId);
                var viewMapCanvas = jQuery('#view_map_canvas' + tagId);
                var viewEventDialog = jQuery('#ViewEventDialog_Tag' + tagId + '_Timeline' + tagId); 

                // Prevent sortable tag bands, shouldn't be able to edit tag bands in view mode.
                jQuery('#TimelineBase_Timeline' + timeline.id).sortable({
                    disabled: true
                }); 
               
                /***** geotagging ****/
                var latId = 'oldview_lat'+tagId;
                var lngId = 'oldview_lng'+tagId;
                var addressId = 'view_address'+tagId;
                var scopeId = 'view_scope'+tagId;
                var canvasId = 'view_map_canvas'+tagId;
                var eid = eventId;
               
                // Toggle map.
                jQuery('#view_showMap' + tagId).data('tagId', tagId).data('tId',this.id);

                jQuery('#view_showMap' + tagId).click(function(event) {
                    event.stopPropagation();
                    var eventObj = jQuery(this).data('eventObj');
                    var tagId = jQuery(this).data('tagId');
                    var tId = jQuery(this).data('tId');
                    var viewEventDialog = jQuery('#ViewEventDialog_Tag' + tagId + '_Timeline' + tId); 
                    var showMapButton = jQuery('#view_showMap' + tagId);
                    var viewMapCanvas = jQuery('#view_map_canvas' + tagId);
                    const spacing = 5; // Extra height from last <tr>.

                    if (viewMapCanvas.css('display') == 'none') {
                        // Re-adjust height of viewEventDialog box.
                        viewEventDialog.css('height', viewEventDialog.height() + spacing + viewMapCanvas.height());
                        viewEventDialog.parent().css('height', viewEventDialog.parent().height() + spacing + viewMapCanvas.height());
                        viewEventDialog.parent().parent().css('height', viewEventDialog.parent().height() + 1); //+1 accounts for uneven heights
                        viewMapCanvas.css('display', 'block');
                        showMapButton.val('Hide Map');
    
                        // Initialize map after resizing in order to map sure everything is centered and loaded.
                        initializeGeotag("view_map_canvas"+tagId);
                        var latId = 'oldview_lat'+tagId;
                        var lngId = 'oldview_lng'+tagId;
                        var addressId = 'view_address'+tagId;
                        var scopeId = 'view_scope'+tagId;
                        var canvasId = 'view_map_canvas'+tagId;
                        var eid = eventId;
                    // Get the address by reverse geocoding to re-center the map.
                    if (jQuery('#view_lat' + tagId).html() != "" &&
                        jQuery('#view_lat' + tagId).html() != 0 &&
                        jQuery('#view_lng' + tagId).html() != "" &&
                        jQuery('#view_lng' + tagId).html() != 0 ) {
                            // Must call again in order to recenter map.
                            codeLocation('latlng', latId, lngId, addressId, scopeId, eid, 1);
                        }
                  } else {
                       viewEventDialog.css('height', viewEventDialog.height() - spacing - viewMapCanvas.height());
                       viewEventDialog.parent().css('height', viewEventDialog.parent().height() - spacing - viewMapCanvas.height());
                       viewEventDialog.parent().parent().css('height', viewEventDialog.parent().parent().height() - spacing - viewMapCanvas.height());
                       viewMapCanvas.css('display', 'none'); 
                       showMapButton.val('Show Map'); 
                   } 
                });

                // Populate geotagging info.
                if (!isNaN(eventObj.getLat()) && !isNaN(eventObj.getLng())) {
                    jQuery('#view_lat'+tagId).html(eventObj.getLat());
                    jQuery('#view_lng'+tagId).html(eventObj.getLng());
                    jQuery('#oldview_lat' + tagId).val(eventObj.getLat());
                    jQuery('#oldview_lng' + tagId).val(eventObj.getLng());

                    // Get the address by reverse geocoding the latitude and longitude.
                    if (jQuery('#view_lat' + tagId).html() != "" &&
                            jQuery('#view_lat' + tagId).html() != 0 &&
                            jQuery('#view_lng' + tagId).html() != "" &&
                            jQuery('#view_lng' + tagId).html() != 0 ) {
                        codeLocation('latlng', latId, lngId, addressId, scopeId, eid, 1);
                        jQuery('#view_address' + tagId).html(jQuery('#oldview_address' + tagId).val());

                        if ( typeof eventObj.getScope() != "undefined" ) {
                            console.log("scope=%s", eventObj.getScope());
                            jQuery('#scope' + tagId + ' option[value="'+eventObj.getScope()+'"]').attr('selected', 'selected');
                        } else {
                            jQuery('#scope' + tagId).removeAttr('options')
                            jQuery('#scope' + tagId).val('[Empty]');
                        }
                    }
                } else {
                    console.log('No geotag information available');
                }

                /***** end geotagging ****/

                jQuery('#view_label' + tagId).html(eventObj.getComment());

                // Attach listeners.
                jQuery('#ViewEventDialog_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.stopPropagation();
                    event.preventDefault();
                });

                // If Close button clicked, delete the dialog.
                jQuery('#ViewEventDialogCloseButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId).data('eventId', eventId);

                jQuery('#ViewEventDialogCloseButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) { 
                    event.stopPropagation();
                    event.preventDefault();
                    var tagId = jQuery(event.target).data('tagId');
                    delete timeline.viewEventDialogOpen[tagId];
                    timeline.redraw();
                });
 
                // If Previous button clicked, delete the dialog, set the event ID and redraw.
                jQuery('#ViewEventDialogPreviousButton_Tag' + tagId + '_Timeline' + this.id).data('tagId', tagId).data('eventId', eventId);
                jQuery('#ViewEventDialogPreviousButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var eventsForThisTag = {list: []};
                    var prevEventId = {id : undefined};
                    var nextEventId = {id : undefined};
                    var eventId = jQuery(event.target).data('eventId');
                    var tagId = jQuery(event.target).data('tagId');
                    console.log(eventId, tagId);
 
                    try {
                        timeline.sortEvent(eventsForThisTag, prevEventId, nextEventId, eventId, tagId);
                        timeline.viewEventDialogOpen[tagId] = prevEventId.id;
                        jQuery('#ViewEventDialog_Tag' + tagId + '_Timeline' + timeline.id).remove();
                        timeline.selectEvent(timeline.findEvent(prevEventId.id));
                        timeline.redraw();
                    } catch (err) {
                        timeline.handleError(err);
                    }
                });
                
                // If Next button clicked, delete the dialog, set the event ID and redraw.
                jQuery('#ViewEventDialogNextButton_Tag' + tagId + '_Timeline' + this.id).data('tagId',tagId).data('eventId', eventId);           
                jQuery('#ViewEventDialogNextButton_Tag' + tagId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var eventsForThisTag = {list: []};
                    var prevEventId = {id : undefined};
                    var nextEventId = {id : undefined};                 
                    var eventId = jQuery(event.target).data('eventId');
                    var tagId = jQuery(event.target).data('tagId');
                    try {
                        timeline.sortEvent(eventsForThisTag, prevEventId, nextEventId, eventId, tagId); 
                        timeline.viewEventDialogOpen[tagId] = nextEventId.id;
                        jQuery('#ViewEventDialog_Tag' + tagId + '_Timeline' + timeline.id).remove();
                        timeline.selectEvent(timeline.findEvent(nextEventId.id));
                        timeline.redraw();
                    } catch (err) {
                        timeline.handleError(err);
                    }
                });
              
                // Show view dialog box by changing parent heights to accommodate it.
                var viewEventDialog = jQuery('#ViewEventDialog_Tag' + tagId + '_Timeline' + this.id);
                var level = this.findEvent(eventId).getLevel();
                var tagLevel = this.findTag(tagId).getLevel();
                var baseHeight = 22; 
                var viewDialogTop = level * baseHeight;
                viewEventDialog.css('top', viewDialogTop);
                viewEventDialog.parent().css('height', viewDialogTop  +  viewEventDialog.height());
                viewEventDialog.parent().parent().css('height', viewDialogTop  +  viewEventDialog.height());
            }
        } else {
            // Remove viewEventDialog box.
            var viewEventDialog = jQuery('#ViewEventDialog_Tag' + tagId + '_Timeline' + this.id);
            var baseHeight = 22; 
            var level = this.findTag(tagId).getLevel();
            viewEventDialog.parent().css('height', baseHeight * level);
            viewEventDialog.parent().parent().css('height', baseHeight * level);     
            viewEventDialog.remove();
            var editTag = jQuery('#EditTagDialog_Tag' + tagId + '_Timeline' + this.id);
            if (editTag.height() !== null || editTag.height() !== undefined) {
                jQuery('#TagEventBand_Tag' + tagId + '_Timeline' + timeline.id).height(parseInt(editTag.height(), 10));                    
            }
        }

        // Process each event

        var allEvents = jQuery.merge([], this.events);
        for (var tid in this.recordingEvents)
            allEvents.push(this.recordingEvents[tid]);
        for (var eventIdx in allEvents) {
            // Only proceed with events of the current tag
            
            if (allEvents[eventIdx].getTag().getId() != tagId)
                continue;
            
            var eventId = allEvents[eventIdx].getId();
            
            // Create event bar (if it does not exist) for this event
            
            if (jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id).length == 0) {
                var str = '';
                str += '<div id="EventBar_Event' + eventId + '_Timeline' + this.id +'" class="EventBar" title="Double-click to edit event; drag left or right edge to resize">';
                str += '<div id="EventBarText_Event' + eventId + '_Timeline' + this.id +'" class="EventBarText"></div>';
                str += '</div>';
                jQuery('#EventBand_Tag' + tagId + '_Timeline' + this.id).append(str);

                jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id).data('eventId', eventId);
                
                jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id).resizable({
                    containment: 'parent',
                    handles: 'e,w',
                    start: function(startEvent) {
                        if (timeline.readOnly)
                            return;
                        
                        var eventId = jQuery(this).data('eventId');
                        var eventObj = timeline.findEvent(eventId);
                        timeline.resizeEventObj = eventObj.deepCopy();
                        timeline.sendMessage(timeline, 'eventBarResizeStart', eventObj);
                    },
                    resize: function(resizeEvent) {
                        if (timeline.readOnly)
                            return;
                        
                        var eventId = jQuery(this).data('eventId');
                        var eventObj = timeline.findEvent(eventId);
                        //var eventObj = timeline.resizeEventObj;
                        
                        var startTime = timeline.pixelToSecond(jQuery(this).position().left, false);
                        var endTime = startTime + timeline.pixelToSecond(jQuery(this).width(), true);
                        eventObj.setStartTime(Math.round(startTime * 1000) / 1000);
                        eventObj.setEndTime(Math.round(endTime * 1000) / 1000);
                        
                        var pixel = resizeEvent.pageX - jQuery('#TimeMarkerPanel_Timeline' + timeline.id).offset().left;
                        var time = timeline.pixelToSecond(pixel);
                        timeline.setCurrentTime(time);
                        timeline.redraw();
                        timeline.sendMessage(timeline, 'timeChanged', time);
                    },
                    stop: function(stopEvent) {
                        if (timeline.readOnly)
                            return;
                        
                        var eventId = jQuery(this).data('eventId');
                        var eventObj = timeline.findEvent(eventId).deepCopy();
                        try {
                            timeline.editEvent(eventObj, timeline.resizeEventObj.getStartTime(), timeline.resizeEventObj.getEndTime(), undefined, eventObj.getStartTime(), eventObj.getEndTime(), undefined, true, undefined, undefined, undefined);
                        } catch (err) {
                            timeline.handleError(err);
                        }
                        delete timeline.resizeEventObj;
                        timeline.redraw();
                        timeline.sendMessage(timeline, 'eventBarResizeStop', eventObj);
                    }
                });

                if (timeline.readOnly)
                    jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id).resizable("disable");
                
                // Double-clicking on the event bar brings up the Edit Event dialog
                
                jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id).click(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    console.log("click on eventbar_event");
                   
                    // If a submission has already been made, replace Edit Event dialog with View Event dialog.
                    if (timeline.readOnly || timeline.readOnlyGroup) {
                        if (jwplayer().getState() == "PLAYING") {
                            jwplayer().pause();
                        }

                        var eventId = jQuery(this).data('eventId');
                        var eventObj = timeline.findEvent(eventId);
                        var viewEventDialog = jQuery('#ViewEventDialogTag_Tag' + tagId + '_Timeline' + this.id);
                        if (typeof timeline.viewEventDialogOpen === 'undefined') {
                            timeline.viewEventDialogOpen = {};
                        }
                        if (jQuery('#ViewEventDialog_Tag' + eventObj.getTag().getId() + '_Timeline' + timeline.id).length !== 0) {
                            sameTag = timeline.viewEventDialogOpen[eventObj.getTag().getId()];
                        }
                        timeline.viewEventDialogOpen[eventObj.getTag().getId()] = eventId;
                        timeline.setTimelineMarker();
                        timeline.redraw(); 
                    }                        
                 
                    if (clicks == 0 || clicks%2 == 2 || true) {
                        
                        if (jwplayer().getState() == "PLAYING")
                            jwplayer().pause();

                        var eventId = jQuery(this).data('eventId');
                        var eventObj = timeline.findEvent(eventId);
                        if(typeof timeline.editEventDialogOpen === "undefined") {
                                        timeline.editEventDialogOpen = {};
                        }
                        timeline.selectEvent(eventObj);
                                timeline.setCurrentTime(eventObj.getStartTime(), true);
                        if(jQuery('#EditEventDialog_Tag' + eventObj.getTag().getId() + '_Timeline' + timeline.id).length !== 0) {
                            sameTag = timeline.editEventDialogOpen[eventObj.getTag().getId()];
                        }
                        timeline.sendMessage(timeline, 'timeChanged', eventObj.getStartTime());
                        timeline.editEventDialogOpen[eventObj.getTag().getId()] = eventId;
                        timeline.setTimelineMarker();
                        timeline.redraw();
                    }   
                    
                    //sameTag = undefined;
                });
            }
            
            // Set the left position and width of both event bar containment and event bar
            var startTime = allEvents[eventIdx].getStartTime();
            var duration = allEvents[eventIdx].getEndTime() - allEvents[eventIdx].getStartTime();
            jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id)
            .css('left', this.secondToPixel(startTime))
            .css('width', this.secondToPixel(duration, true));
            jQuery('#EventBarText_Event' + eventId + '_Timeline' + this.id).text(allEvents[eventIdx].getComment());

            // Set the vertical position of the EventBar.
            var eventBar = jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id);
            var level = this.findTag(tagId).getLevel();

            var baseHeight = 22;
            // Subtract 1 from level since all levels start at level 1 by default.
            eventBar.css('top', baseHeight * (allEvents[eventIdx].getLevel()-1));
            
            if (eventBar.parent().parent().data('oldlevel') !== level) {
                eventBar.parent().css('height', level * baseHeight); 
                eventBar.parent().parent().css('height', level * baseHeight);
                eventBar.parent().parent().data('oldlevel', level);
            }

            // Set background color (depending on whether the event is selected)
            
            var eventSelected = false;
            for (var idx in this.selectedEvents) {
                if (this.selectedEvents[idx].getId() == eventId) {
                    eventSelected = true;
                    break;
                }
            }
            
            var eventColor = allEvents[eventIdx].getTag().getColor();
            if (eventSelected) {
                jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id)
                .addClass('EventBarSelected')
                .css('background-color', eventColor);
	    } else {
                jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id)
                .removeClass('EventBarSelected')
                .css('background-color', eventColor);
                
            }
            
            // Add start/end times to the tooltip of event bar's resize handles
            
            var myRound = function(x) {
                return Math.floor(x * 10) / 10;
            }
            
            jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id).find('.ui-resizable-w').attr("title", "Start time: " + myRound(startTime));
            jQuery('#EventBar_Event' + eventId + '_Timeline' + this.id).find('.ui-resizable-e').attr("title", "End time: " + myRound(startTime + duration));
        }
    }
}
CSAVTimeline.prototype.sortEvent = function(eventsForThisTag, prevEventId, nextEventId, eventId, tagId) {  
    for (var idx in this.events) {
	if (this.events[idx].getTag().getId() == tagId)
	    eventsForThisTag.list.push(this.events[idx]);
    }
    eventsForThisTag.list.sort(function(a, b) {
	return (a.getStartTime() - b.getStartTime()) || (a.getEndTime() - b.getEndTime());
    });
    for (var idx in eventsForThisTag.list) {
	if (eventsForThisTag.list[idx].getId() == eventId) {
	    if (idx > 0)
		prevEventId.id = eventsForThisTag.list[Number(idx) - 1].getId();
	    if (idx < eventsForThisTag.list.length - 1)
		nextEventId.id = eventsForThisTag.list[Number(idx) + 1].getId();
	}
    }
}
               
CSAVTimeline.prototype.setTimelineMarker = function() {
    var eventDialogOpen = false;
    for (var idx in this.addEventDialogOpen) {
	var eventDialogOpen = true;
	break;
    }
    for (var idx in this.editEventDialogOpen) {
	var eventDialogOpen = true;
	break;
    }

    if (this.getCurrentTime() >= 0 && !eventDialogOpen) {
	var boundaryLeft = jQuery('#LeftMarker_Timeline' + this.id).position().left;
	var boundaryRight = jQuery('#RightMarker_Timeline' + this.id).position().left;
	var handleLeft = boundaryLeft + this.secondToPixel(this.getCurrentTime());
	var handleRight = handleLeft + jQuery('#CurrentTimeBarHandle_Timeline' + this.id).width();
				
	if (handleLeft < boundaryLeft || boundaryRight < handleRight ) {
		this.leftRatio = (this.getCurrentTime() - this.minTime) / (this.maxTime - this.minTime);
		delete this.noRedraw.marker;
		this.redraw();
		return;
	} else {
		jQuery('#CurrentTimeBarHandle_Timeline' + this.id + ',#CurrentTimeBar_Timeline' + this.id)
		.show()
		.css('left', handleLeft + 1);
	}
    } else {
	jQuery('#CurrentTimeBarHandle_Timeline' + this.id + ',#CurrentTimeBar_Timeline' + this.id).hide();
    }
    this.noRedraw.currentTime = true;
}

CSAVTimeline.prototype.addTag = function(id, tag, color, useAJAX, level) {
    var tagObj = this.findTag(tag);
    if (tagObj !== undefined) {
        throw "CSAVTimeline.prototype.addTag: Tag \"" + tag + "\" already exists.";
    }
    if (tag instanceof CSAVTimelineTag) {
        tagObj = tag;
    } else if (typeof tag === "string") {
        tagObj = new CSAVTimelineTag({"id": id, "timeline": this, "name": tag, "color": color, "level": level});
    } else {
        throw "CSAVTimeline.prototype.addTag: Tag \"" + tag + "\" is invalid.";
    }

    // Add a tag into the database via AJAX.
    if (useAJAX) {
        var error = undefined;
        jQuery.ajax({
            type: "POST",
            url: "database.php", 
            data : {
        		"c1_command": "addtag", 
        		"c1_clipid": this.clipId,
        		"c1_name": tagObj.getName(),
                "c1_color": tagObj.getColor(),
                "c1_userid": this.userId,
                "c1_groupid": this.groupId,
                "c1_level": tagObj.getLevel()
        	},
            dataType: "json",
            async: false,
            success: function(jsonData, textStatus) {
                if (!jsonData instanceof Array) {
                    error = new Error();
                    error.message = "error when receiving data from server";
                    return;
                }
                
                if (jsonData[0]["success"] !== true) {
                    error = new Error();
                    if (typeof jsonData[0]["errortype"] != "undefined") {
                        error.errortype = jsonData[0]["errortype"];
                    }
                    if (typeof jsonData[0]["message"] != "undefined") {
                        error.message = jsonData[0]["message"];
                    }
                    return;
                }

                tagObj.setId(Number(jsonData[0]["id"]));
            }
        });
        if (typeof error != "undefined")
            throw error;
    }

    this.tags.push(tagObj);

    //this.redrawTag(tagObj);
    
    //this.adjustRightPanelHeight();
    
    this.sendMessage(this, "tagAdded", tagObj);
    
    return tagObj;
}

CSAVTimeline.prototype.addEvent = function(id, tag, startTime, endTime, comment, useAJAX, latitude, longitude, scope, level) {
    var tagObj = this.findTag(tag);
    if (tagObj === undefined) {
        throw "CSAVTimeline.prototype.addEvent: Tag \"" + tagObj + "\" does not exist";
    }

    console.log("adding event lat=%d lng=%d", latitude, longitude);
    var newEvent = new CSAVTimelineEvent({
    "id": id,
    "tag": tagObj, 
    "startTime": Number(startTime), 
    "endTime": Number(endTime), 
    "comment": comment,
    "latitude": Number(latitude),
    "longitude": Number(longitude),
    "scope": scope,
    "level": level
    });
    
    //
    
    if (useAJAX) {
        var error = undefined;
        jQuery.ajax({
            type: "POST",
            url: "database.php", 
            data: {
                "c1_command": "addevent", 
                "c1_tagid": tagObj.getId(), 
                "c1_starttime": startTime,
                "c1_endtime": endTime,
                "c1_content": comment,
                "c1_userid": this.userId,
                "c1_groupid": this.groupId,
                "c1_latitude": latitude,
                "c1_longitude": longitude,
                "c1_scope": scope,
                "c1_level": level
            }, 
            dataType: "json",
            async: false,
            success: function(jsonData, textStatus) {
                if (!jsonData instanceof Array) {
                    error = new Error();
                    error.message = "error when receiving data from server";
                    return;
                }
                    
                if (jsonData[0]["success"] !== true) {
                    error = new Error();
                    if (typeof jsonData[0]["errortype"] != "undefined")
                        error.errortype = jsonData[0]["errortype"];
                    if (typeof jsonData[0]["message"] != "undefined")
                        error.message = jsonData[0]["message"];
                    return;
                }
                
                newEvent.setId(jsonData[0]["id"]);
            }
        });
        if (typeof error != "undefined")
            throw error;
    }
    
    //
    
    this.events.push(newEvent);
    
    //this.redrawEvent(newEvent);
    
    this.sendMessage(this, "eventAdded", newEvent);

    return newEvent;
}

CSAVTimeline.prototype.addListener = function(action, listener) {
    if (this.listeners[action] === undefined)
        this.listeners[action] = [];
    
    this.listeners[action].push(listener);
}

CSAVTimeline.prototype.editTag = function(tag, oldName, oldColor, newName, newColor, sendUpdate, level) {
    var tagObj = this.findTag(Number(tag));
    if (tagObj === undefined) {
        throw "Tag \"" + tag + "\" does not exist.";
    }
    
    for (var idx in this.tags) {
        if (this.tags[idx].getId() != tagObj.getId() && this.tags[idx].getName() == tagObj.getName()) {
            throw "Tag \"" + newName + "\" already exists.";
        }
    }
    
    var changed = false;
    var data = {
        "c1_command": "edittag",
        "c1_id": tagObj.getId()
    };
    if (typeof newName !== "undefined") {
        data["c1_name"] = newName;
        data["c1_oldname"] = oldName;
        changed = true;
    }
    if (typeof newColor !== "undefined") {
        data["c1_color"] = newColor;
        data["c1_oldcolor"] = oldColor;
        changed = true;
    }
    if (typeof level !== "undefined") {
        data["c1_level"] = level;
        changed = true;
    }   
    
    // Update the database via AJAX.
    if (changed && sendUpdate) {
        var error = undefined;
        jQuery.ajax({
            type: "POST",
            url: "database.php", 
            data: data, 
            dataType: "json",
            async: false,
            success: function(jsonData, textStatus, xhr) {
                if (!jsonData instanceof Array) {
                    error = new Error();
                    error.message = "error when receiving data from server";
                    return;
                }
                
                if (jsonData[0]["success"] !== true) {
                    error = new Error();
                    if (typeof jsonData[0]["errortype"] != "undefined") {
                        error.errortype = jsonData[0]["errortype"];
                    }
                    if (typeof jsonData[0]["message"] != "undefined") {
                        error.message = jsonData[0]["message"];
                    }
                    return;
                }
            },
            error: function(xhr, status, error) {
                    console.log(xhr);
                    console.log("Details: " + status + "\nError:" + error);
            }
        });
        if (typeof error != "undefined") {
            throw error;
        }
    }
    
    if (typeof newName != "undefined") {
        tagObj.setName(newName);
    }
    if (typeof newColor != "undefined") {
        tagObj.setColor(newColor);
    }
    if (typeof level != "undefined") {
        tagObj.setLevel(level);
    }
    
    this.sendMessage(this, "tagEdited", tagObj);
    
    return tagObj;
}

CSAVTimeline.prototype.editEvent = function(event, oldStartTime, oldEndTime, oldComment, newStartTime, newEndTime, newComment, sendUpdate, latitude, longitude, scope, level) {    
    var eventObj = this.findEvent(event);
    if (eventObj === undefined) {
        throw "Event \"" + event + "\" does not exist.";
    }
    
    var changed = false;
    var data = {
        "c1_command": "editevent", 
        "c1_id": eventObj.getId()
    };
    if (typeof newStartTime != "undefined") {
        data["c1_starttime"] = newStartTime;
        data["c1_oldstarttime"] = oldStartTime;
        changed = true;
    }
    if (typeof newEndTime != "undefined") {
        data["c1_endtime"] = newEndTime;
        data["c1_oldendtime"] = oldEndTime;
        changed = true;
    }
    if (typeof newComment != "undefined") {
        data["c1_content"] = newComment;
        data["c1_oldcontent"] = oldComment;
        changed = true;
    }
    if (typeof latitude != "undefined") {
        data["c1_latitude"] = latitude;
    }
    if (typeof longitude != "undefined") {
        data["c1_longitude"] = longitude;
    }
    if (typeof scope != "undefined") {
        data["c1_scope"] = scope;
    } 
    if (typeof level != "undefined") {
        data["c1_level"] = level;
    }

    // Update the database via AJAX.
    if (changed && sendUpdate) {
        var error = undefined;
        jQuery.ajax({
            type: "POST",
            url: "database.php", 
            data: data, 
            dataType: "json",
            async: false,
            success: function(jsonData, textStatus) {
                if (!jsonData instanceof Array) {
                    error = new Error();
                    error.message = "error when receiving data from server";
                    return;
                }
                
                if (jsonData[0]["success"] !== true) {
                    error = new Error();
                    if (typeof jsonData[0]["errortype"] != "undefined") {
                        error.errortype = jsonData[0]["errortype"];
                    }
                    if (typeof jsonData[0]["message"] != "undefined") {
                        error.message = jsonData[0]["message"];
                    }
                    return;
                }
            }
        });
        if (typeof error != "undefined") {
            throw error;
        }
    }

    if (typeof newTagObj != "undefined") {
        eventObj.setTag(newTagObj);
    }
    if (typeof newStartTime != "undefined") {
        eventObj.setStartTime(newStartTime);
    }
    if (typeof newEndTime != "undefined") {
        eventObj.setEndTime(newEndTime);
    }
    if (typeof newComment != "undefined") {
        eventObj.setComment(newComment);
    }
    if (typeof latitude != "undefined") {
        eventObj.setLat(latitude);
    }
    if (typeof longitude != "undefined") {
        eventObj.setLng(longitude);
    }
    if (typeof scope != "undefined") {
        eventObj.setScope(scope);
    }
    if (typeof level != "undefined") {
        eventObj.setLevel(level);
    }

    this.sendMessage(this, "eventEdited", eventObj);

    return eventObj;
}

CSAVTimeline.prototype.findTag = function(tag) {
    for (var idx in this.tags) {
        if (tag instanceof CSAVTimelineTag && this.tags[idx].getId() == tag.getId()) {
            return this.tags[idx];
        }
        if (typeof tag === "number" && this.tags[idx].getId() == tag) {
            return this.tags[idx];
        }
        if (typeof tag === "string" && this.tags[idx].getName().toLowerCase() === tag.toLowerCase()) {
            return this.tags[idx];
        }
    }
    
    return undefined;
}

CSAVTimeline.prototype.findEvent = function(event) {
    for (var idx in this.events) {
        if (event instanceof CSAVTimelineEvent && this.events[idx].getId() == event.getId())
            return this.events[idx];
        
        if (typeof event === "number" && this.events[idx].getId() === event)
            return this.events[idx];
    }
    
    return undefined;
}

CSAVTimeline.prototype.getTags = function() {
    return this.tags;
}

CSAVTimeline.prototype.getCurrentTime = function() {
    return this.currentTime;
}

CSAVTimeline.prototype.getEventContainment = function(event) {
	var eventObj = this.findEvent(event);
	if (typeof eventObj === "undefined")
		return undefined;
	
	var containment = {min: this.minTime, max: this.maxTime};
	
	for (var idx in this.events) {
		if (this.events[idx].getTag().getId() != eventObj.getTag().getId())
			continue;
		if (this.events[idx].getEndTime() <= eventObj.getStartTime())
			containment.min = Math.max(containment.min, this.events[idx].getEndTime());
		
		if (eventObj.getEndTime() <= this.events[idx].getStartTime())
			containment.max = Math.min(containment.max, this.events[idx].getStartTime());
	}
	
	return containment;
}

CSAVTimeline.prototype.getId = function() {
    return this.id;
}

CSAVTimeline.prototype.getSelectedTags = function() {
    return this.selectedTags;
}

CSAVTimeline.prototype.getSelectedEvents = function() {
    return this.selectedEvents;
}

CSAVTimeline.prototype.getSelector = function() {
    return this.selector;
}

CSAVTimeline.prototype.getZoomFactor = function() {
    return this.zoomFactor;
}

CSAVTimeline.prototype.pixelToSecond = function(pixel, delta) {
    var eventBandWidth = jQuery('#TimeMarkerDigitPanel2_Timeline' + this.id).width();
    
    if (delta) {
        return pixel * (this.maxTime - this.minTime) / (eventBandWidth * this.zoomFactor);
    } else {
        //var eventBarLeftInSec = (typeof this.eventBarLeftInSec !== 'undefined' ? this.eventBarLeftInSec : 0);
        //return pixel * (this.maxTime - this.minTime) / (this.eventBandWidth * this.zoomFactor) + eventBarLeftInSec;
        var leftRatio = (typeof this.leftRatio !== 'undefined' ? this.leftRatio : 0);
        return this.minTime + leftRatio * (this.maxTime - this.minTime) + pixel * (this.maxTime - this.minTime) / (eventBandWidth * this.zoomFactor);
    }
}

CSAVTimeline.prototype.random = function(from, to) {
    return Math.floor(Math.random() * (to - from + 1) + from);
}

CSAVTimeline.prototype.randomId = function() {
    // Return "T" + a 8-digit integer
    // Note that the prefix is the way we distinguish random IDs we make up (has prefix)
    // and IDs that we receive from the web service (no prefix)
    
    return 'T' + this.random(10000000, 99999999);
}

CSAVTimeline.prototype.removeTags = function(compare, sendUpdate) {
    var deletedTags = [];
    var retainedTags = [];
    
    for (var idx in this.tags) {
        var tag = this.tags[idx];
        if (compare(idx, tag)) {
            deletedTags.push(tag);
        } else {
            retainedTags.push(this.tags[idx]);
        }
    }
    
    this.tags = retainedTags;
    
    var error = undefined;
    for (var idx in deletedTags) {
        var tag = deletedTags[idx];

	var tagName = tag.name;
        var conflict = false;
        if (this.recordingEvents[tag.getId()] !== undefined) {
            conflict = true;
        }

        this.removeEvents(function(idx, event) {
            return event.getTag().getId() === tag.getId();
        });
        jQuery("#TagBand_Tag" + tag.getId()).remove();
        jQuery("#EventBand_Tag" + tag.getId()).remove();

        if (sendUpdate) {
            var timeline = this;
            var error = undefined;
            jQuery.ajax({
                type: "POST",
                url: "database.php", 
                data: {
            		"c1_command": "deletetag",
            		"c1_id": tag.getId()
            	}, 
                dataType: "json",
                async: false,
                success: function(jsonData, textStatus) {
                    if (!jsonData instanceof Array) {
                        error = new Error();
                        error.message = "error when receiving data from server";
                        return;
                    }

                    if (jsonData[0]["success"] !== true) {
                        error = new Error();
                        if (typeof jsonData[0]["errortype"] != "undefined") {
                            error.errortype = jsonData[0]["errortype"];
                        }
                        if (typeof jsonData[0]["message"] != "undefined") {
                            error.message = jsonData[0]["message"];
                        }
                        return;
                    }
                                    
                }
            });
            if (typeof error != "undefined")
                throw error;
        }
        this.sendMessage(this, "tagRemoved", tagName, conflict);
    }
}

CSAVTimeline.prototype.removeTag = function(id, sendUpdate) {
    this.removeTags(function(index, tag) {
        return tag.getId() == id;
    }, sendUpdate);
}

CSAVTimeline.prototype.removeEvent = function(id, sendUpdate) {
    this.removeEvents(function(index, event) {
        return event.getId() == id;
    }, sendUpdate);
}

CSAVTimeline.prototype.removeEvents = function(compare, sendUpdate) {
    var removedEvents = [];
    var retainedEvents = [];

    for (var idx in this.events) {
        var event = this.events[idx];
        if (compare(idx, event)) {
            removedEvents.push(event);
        } else {
            retainedEvents.push(event);
        }
    }

    for (var idx in removedEvents) {
        var event = removedEvents[idx];
        
        jQuery("#EventBar_" + event.getId()).remove();
        
        if (sendUpdate) {
            var timeline = this;
            var error = undefined;
            jQuery.ajax({
                type: "POST",
                url: "database.php", 
                data: {
                    "c1_command": "deleteevent", 
                    "c1_id": event.getId()
                }, 
                dataType: "json",
                async: false,
                success: function(jsonData, textStatus) {
                    if (!jsonData instanceof Array) {
                        error = new Error();
                        error.message = "error when receiving data from server";
                        return;
                    }

                    if (jsonData[0]["success"] !== true) {
                        error = new Error();
                        if (typeof jsonData[0]["errortype"] != "undefined")
                            error.errortype = jsonData[0]["errortype"];
                        if (typeof jsonData[0]["message"] != "undefined")
                            error.message = jsonData[0]["message"];
                        return;
                    }
                                    
                    timeline.sendMessage(timeline, "eventRemoved", event);
                }
            });
            if (typeof error != "undefined") {
                throw error;
            }
        }
    }

    this.events = retainedEvents;
}

CSAVTimeline.prototype.secondToPixel = function(second, delta) {
    var eventBandWidth = jQuery('#TimeMarkerDigitPanel2_Timeline' + this.id).width();
    
    if (delta) {
        return Math.round(eventBandWidth * this.zoomFactor * second / (this.maxTime - this.minTime));
    } else {
        //var eventBarLeftInSec = (typeof this.eventBarLeftInSec !== 'undefined' ? this.eventBarLeftInSec : 0);
        //return Math.round(this.eventBandWidth * this.zoomFactor * (second - eventBarLeftInSec) / (this.maxTime - this.minTime));
        var leftRatio = (typeof this.leftRatio !== 'undefined' ? this.leftRatio : 0);
        return Math.round(eventBandWidth * this.zoomFactor / (this.maxTime - this.minTime) * (second - this.minTime - leftRatio * (this.maxTime - this.minTime)));
    }
}

CSAVTimeline.prototype.selectTag = function(tag) {
    return this.selectTags([tag]);
}

CSAVTimeline.prototype.selectTags = function(tags) {
    var tagObjs = [];
    for (var idx in tags) {
        var tagObj = this.findTag(tags[idx]);
        if (tagObj === undefined)
            throw "Tag \"" + tags[idx] + "\" does not exist.";
        tagObjs.push(tagObj);
    }
    
    this.selectedTags = tagObjs;
    
    timeline.sendMessage(timeline, "tagsSelected", tagObjs);
}

CSAVTimeline.prototype.selectEvent = function(event) {
    this.selectEvents([event]);
}

CSAVTimeline.prototype.selectEvents = function(events) {
    var eventObjs = [];
    for (var idx in events) {
        var eventObj = this.findEvent(events[idx]);
        if (eventObj === undefined)
            throw "Event \"" + events[idx] + "\" does not exist.";
        eventObjs.push(eventObj);
    }
    
    this.selectedEvents = [eventObj];
    
    timeline.sendMessage(timeline, "eventsSelected", eventObjs);
}

CSAVTimeline.prototype.selectNoTag = function() {
    this.selectTags([]);
}

CSAVTimeline.prototype.selectNoEvent = function() {
    this.selectEvents([]);
}

CSAVTimeline.prototype.setCurrentTime = function(currentTime, internal) {
    var prevTime = this.currentTime;

    if (currentTime >= 0 || currentTime < 0) {
        if (currentTime < this.minTime) {
            currentTime = this.minTime;
        } else if (currentTime > this.maxTime) {
            currentTime = this.maxTime;
        }
        
        currentTime = Math.round(currentTime * 1000) / 1000;
        
        this.currentTime = currentTime;

        for (var tagId in this.recordingEvents) {
            this.recordingEvents[tagId].setEndTime(currentTime);
        }

    // If the time given is not a number, set our current time to undefined, and do not show the current time bar.
    } else {
        this.currentTime = undefined;
        
        for (var idx in this.tags) {
            this.stopRecording(this.tags[idx].getName());
        }
    }
	
	timeline.sendMessage(internal ? this : undefined, "timeChanged", this.currentTime);
}

CSAVTimeline.prototype.startRecording = function(tags) {
    var effectiveStartTime = Math.round(this.getCurrentTime() * 1000) / 1000;

    for (var idx in tags) {
        var tagObj = this.findTag(tags[idx]);
        if (tagObj === undefined) {
            continue;
        }
        if (typeof this.recordingEvents[tagObj.getId()] !== "undefined") {
            continue;
        }
        try {
            this.recordingEvents[tagObj.getId()] = new CSAVTimelineEvent({
                id: this.randomId(),
                tag: tagObj,
                startTime: effectiveStartTime,
                endTime: effectiveStartTime,
                level: 1
            });
        } catch (err) {
            timeline.handleError(err);
        }
    }
    
    timeline.redraw(true);
}

CSAVTimeline.prototype.stopRecording = function(tags) {
    var effectiveEndTime = Math.round(this.getCurrentTime() * 1000) / 1000;
    
    for (var idx in tags) {
        var tagObj = this.findTag(tags[idx]);
        if (tagObj === undefined) {
            continue;
        }
        if (typeof this.recordingEvents[tagObj.getId()] === "undefined") {
            continue;
        }
        var event = this.recordingEvents[tagObj.getId()];
        var tagId = tagObj.getId();
        delete this.recordingEvents[tagObj.getId()];
        jQuery("#EventBar_Event" + event.getId() + "_Timeline" + this.id).remove();
        try {
            event = this.addEvent(undefined, tagObj.getName(), event.getStartTime(), effectiveEndTime, undefined, true, undefined, undefined, undefined, event.getLevel());
            this.assignLevel(tagId, event.getStartTime(), effectiveEndTime, event.getId());
        } catch (err) {
            timeline.handleError(err);
        }
    }
}

CSAVTimeline.prototype.sortEventByLevel = function (eventsForThisTag, prevEventId, nextEventId, eventId, tagId, level) {
    for (var idx in this.events) {
        if (this.events[idx].getTag().getId() == tagId  &&  this.events[idx].getLevel() == level) {
            eventsForThisTag.list.push(this.events[idx]);
        }
    }
    eventsForThisTag.list.sort(function(a, b) {
        return (a.getStartTime() - b.getStartTime()) || (a.getEndTime() - b.getEndTime());
    });
    for (var idx in eventsForThisTag.list) {
        if (eventsForThisTag.list[idx].getId() == eventId) {
            if (idx > 0) {
                prevEventId.id = eventsForThisTag.list[Number(idx) - 1].getId();
            }
            if (idx < eventsForThisTag.list.length - 1) {
                nextEventId.id = eventsForThisTag.list[Number(idx) + 1].getId();
            }
        }
    }
}

CSAVTimeline.prototype.assignLevel = function (tagId, startTime, endTime, eventId) {
    var level;
    var tagObj = this.findTag(tagId);
    var eventObj = this.findEvent(eventId);
  
    for (level = 1;; level++) {
        var eventsForThisTag = {list: []};
        var prevEventId = {id: undefined};
        var nextEventId = {id: undefined};
        timeline.sortEventByLevel(eventsForThisTag, prevEventId, nextEventId, eventId, tagId, level);

        // Case when the previous event overlaps with the new event.
        if (prevEventId.id !== undefined) {
            var prevEndTime = timeline.findEvent(prevEventId.id).getEndTime();
            var prevEventLevel = this.findEvent(prevEventId.id).getLevel();
            if (prevEndTime >= startTime && level == prevEventLevel) {
                eventObj.setLevel(level + 1);
                continue;

            // Case when the the previous event doesn't overlap but the next event does.
            } else if (nextEventId.id !== undefined) {
                var nextStartTime = timeline.findEvent(nextEventId.id).getStartTime();
                var nextEventLevel = this.findEvent(nextEventId.id).getLevel();
                if (nextStartTime <= endTime && level == nextEventLevel) {
                    eventObj.setLevel(level + 1);
                    continue;
                } else {
                    break;
                }

            } else {
                break;
            }

        // Case when the next even overlaps with the new event and a previous event doesn't exist.
        } else if (nextEventId.id !== undefined) {
            var nextStartTime = timeline.findEvent(nextEventId.id).getStartTime();
            var nextEventLevel = this.findEvent(nextEventId.id).getLevel();
            if (nextStartTime <= endTime && level == nextEventLevel) {
                eventObj.setLevel(level + 1);
                continue;
            } else {
                break;
            }

        } else {
            break;
        }
    }

    this.editEvent(eventId, startTime, endTime, undefined, startTime, endTime, undefined, true, undefined, undefined, undefined, level);
    if (level > tagObj.getLevel()) {
        var name = tagObj.getName();
        var color = tagObj.getColor();
        this.editTag(tagId, name, color, name, color, true, level);
    }
}

CSAVTimeline.prototype.sendMessage = function(sender, action) {
    var params = [];
    for (var i = 2; i < arguments.length; i++) {
        params.push(arguments[i]);
    }
    
    if (this.listeners[action] !== undefined) {
        for (var idx in this.listeners[action]) {
            this.listeners[action][idx].apply(sender, params);
        }
    }
        
    if (this.listeners[""] !== undefined) {
        for (var idx in this.listeners[""]) {
            this.listeners[""][idx].apply(sender, params);
        }
    }
}
