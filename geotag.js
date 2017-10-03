//include the google maps api
//var s = document.createElement('script');
//s.setAttribute('type','text/javascript');
//s.setAttribute('src', "http://maps.googleapis.com/maps/api/js?&sensor=false");


if( typeof google == "undefined")
{
    //google maps api failed to load
    alert("The google maps api failed to load. The geocoding features will not work. You can still manually enter and save latitude and longitude.");
}
else {
var map;
var marker;
var mresults;
var geocoder = new google.maps.Geocoder();
}

/* initializeGeotag(mapid) ************************
***** Initializes the geocoder and the map ******
**      mapid       The id of the element that holds the map **
**
***************************************************/

function initializeGeotag(mapid) {
    if (typeof google == "undefined")
    {
        //geocoder didnt load correctly, so do nothing
        return -1;
    }
    var options = {
        center: new google.maps.LatLng(34.0687617, -118.4449415),   //default center is UCLA
        zoom: 7,
        mapTypeId: google.maps.MapTypeId.ROADMAP
    };

    map = new google.maps.Map(document.getElementById(mapid), options);
    marker = new google.maps.Marker({
        map: map,
        position: new google.maps.LatLng(-34, 150),
        draggable: true
    });
    marker.infowindow = new google.maps.InfoWindow();
   
    google.maps.event.addDomListener(document.getElementById(mapid), 'change', function() {
        console.log('RESIZING');
        google.maps.event.trigger(map, 'resize');
        google.maps.event.trigger(map, 'resize');

    });

}

/* codeLocation(type, latId, lngId, addressId, viewFlag) ************************
***** Finds the address from a lat and lng or the lat 
**    and lng from and address 
**
**      type        The type of request. Either "latlng" to find address from
**                  latitude and longitude, or "address to find lat and lng
**                  from address
**      latId       The id of the element that is used to hold and display
**                  the latitude
**      lngId       The id of the element that is used to hold and display
**                  the longitude
**      addressId   The id of the element that is used to hold and display
**                  the address
**      viewFlag    Determines whether or not info is for viewEventDialog or
**                  EditEventDialog
***************************************************/
 

function codeLocation(type, latId, lngId, addressId, scopeId, eid, viewFlag) {
    if (typeof google == "undefined")
    {
        //geocoder didnt load correctly, so do nothing
        return -1;
    }
    console.log("coding a %s", type);
    console.log("latId=%s, lngId=%s, addressId=%s", latId, lngId, addressId);
    switch (type)
    {
        case 'address':
            var address = document.getElementById(addressId).value;
            geocoder.geocode( {'address': address}, function (results, status) {
                console.log("executing callback");
                processGeocode(results, status, latId, lngId, addressId, scopeId, eid, viewFlag);
            });
        break;
        case 'latlng':
            var lat = document.getElementById(latId).value;
            var lng = document.getElementById(lngId).value;
            var latlng = new google.maps.LatLng(lat, lng);
           geocoder.geocode( {'latLng': latlng}, function (results, status) {
               console.log("executing callback");
	       processGeocode(results, status, latId, lngId, addressId, scopeId, eid, viewFlag);
            });
        break;
        default:
            alert("Not a valid request");
    };
}

/* processGeocode(results, status, latId, lngId, addressId, viewFlag) ***************
***** Receives the results from a geocode request and updates the lat, lng, and
**    address boxes, the marker, and the info window
**
**      results     An array of the results from a google geocode request
**
**      status      The status returned by the geocode request
**                 
**      latId       The id of the element that is used to hold and display
**                  the latitude
**      lngId       The id of the element that is used to hold and display
**                  the longitude
**      addressId   The id of the element that is used to hold and display
**                  the address 
**      viewFlag    Determines whether or not info is for viewEventDialog or
**                  EditEventDialog
***************************************************/
 
function processGeocode(results, status, latId, lngId, addressId, scopeId, eid, viewFlag) {
    if (status == google.maps.GeocoderStatus.OK) {
        console.log("latId=%s, lngId=%s, addressId=%s, scopeId=%s", latId, lngId, addressId, scopeId);
        var latlng = results[0].geometry.location;
        var address = results[0].formatted_address;
        
        if (!viewFlag) {
            document.getElementById(addressId).value = address;
        } else {
            document.getElementById(addressId).innerHTML = address;
        }

        document.getElementById(latId).value = latlng.lat();
        document.getElementById(lngId).value = latlng.lng();

        if (marker !== undefined) {
            marker.setPosition(latlng);
            marker.infowindow.setContent(address + "<br/>" + latlng.lat() + ", " + latlng.lng());
            google.maps.event.addListener(marker, "dragend", function() {
                latlng = marker.getPosition();
                document.getElementById(addressId).value = "";
                document.getElementById(latId).value = latlng.lat();
                document.getElementById(lngId).value = latlng.lng();
                marker.infowindow.setContent( "<br/>" + latlng.lat() + ", " + latlng.lng());
                // If the marker is changed, automatically reverse geocode.
                codeLocation('latlng', latId, lngId, addressId, scopeId);
            });
            marker.infowindow.open(map, marker);
            google.maps.event.addListener( marker, "click", function() {
                marker.infowindow.open(map, marker)
            });

            // Every time the address is reverse geocoded (which always happens upon opening an event) geocode to parse
            // the address into the 'results' array. Always fill out the scope here with the results array and change
            // the selected option.
            var eventObj = timeline.findEvent(eid);
            var scope = document.getElementById(scopeId);
	        scope.options.length = 0;
	        var i, j=0;
	        var prevname;
            var inScope = false;
	        for (i=0; i < results[0].address_components.length;i++) {
	            c = results[0].address_components[i];
	            console.log("long_name", c.long_name);
	            if (j ==0 || c.long_name !== prevname) {
		            scope.options[j] = new Option(c.long_name, c.types[0]);
                    if (eventObj !== undefined && eventObj.getScope() !== undefined) {
                        if (eventObj.getScope() == c.types[0]) {
                            inScope = true;
                        }
                    }
		            prevname = scope.options[j].text;
		            j++;
	            }
	        }
            if (inScope) {
                scope.value = eventObj.getScope();
            } else {
                scope.value = scope.options[0].value;
            }
	        
	        mresults = results;
            map.setCenter(latlng);
            map.setZoom(7);
        }
    } else {
        alert( "Geocoder failed due to: " + status );
    }
}

