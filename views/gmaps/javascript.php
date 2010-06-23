<?
list($lat, $lon, $zoom, $type) = $center;
?>
var markers, map;

function gmap_init() {
	map = new google.maps.Map(document.getElementById('<?=$id?>'), {
		zoom: <?=$zoom?>,
		center: new google.maps.LatLng(<?=$lat?>, <?=$lon?>),
		mapTypeId: google.maps.MapTypeId.<?=$type?>,
		<?=substr(json_encode($options), 1, -1)?> 
	});
<?if(!empty($markers)):?>
	
	var markerData = [
<?
// generate the marker javascript
$count = 0;

foreach($markers as $marker):
	if($count++ > 0) echo ",";
	
	$lat = $marker['lat'];
	$long = $marker['lon'];
	
	unset($marker['lat']);
	unset($marker['lon']);
?>
	{
		position: new google.maps.LatLng(<?=$lat?>, <?=$lon?>),
		map: map,
		<?=substr(json_encode($marker), 1, -1)?>
	}
<?endforeach;?>
	];

	markers = {general:[]};
	
	for(var a = 0, m; a < markerData.length; a++) {
		m = new google.maps.Marker(markerData[a]);
		
		if(markerData[a]['category']) {
			if(!markers[markerData[a]['category']]) {
				markers[markerData[a]['category']] = [];
			}
			
			markers[markerData[a]['category']].push(markerData[a])
		} else {
			markers['general'].push(markerData[a])
		}
	}
<?endif;?>
}

window.onload = gmap_init;
