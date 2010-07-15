<?
list($lat, $lon, $zoom, $type) = $center;
?>
var markers, icons = {}, markerData, map;

function gmap_init() {
	map = new google.maps.Map(document.getElementById('<?=$id?>'), {
		center: new google.maps.LatLng(<?=$lat?>, <?=$lon?>),
		mapTypeId: google.maps.MapTypeId.<?=$type?>,
		<?=substr(json_encode(array_merge($options, array('zoom' => $zoom)), 1, -1)?> 
	});

<?
if(!empty($icons)):
	foreach($icons as $icon):
		// load the icon and grab the sizes if they aren't specified
		if(empty($icon['size'])) {
			$icon['size'] = getimagesize(DOCROOT.$icon['url']);
		}
		
		// calculate the position, default to center
		// string options:
		//		- center
		
		if(empty($icon['position'])) {
			$icon['position'] = 'center';
		}
		
		if(!is_array($icon['position'])) {
			switch($icon['position']) {
				case 'center':
					$icon['position'] = array($icon['size'][0] / 2, $icon['size'][1] / 2);
					break;
			}
		}
?>
	icons['<?=$icon['name']?>'] = new google.maps.MarkerImage('<?=$icon['url']?>',
		new google.maps.Size(<?=$icon['size'][0]?>,<?=$icon['size'][1]?>),
		new google.maps.Point(0, 0),
		new google.maps.Point(<?=$icon['position'][0]?>, <?=$icon['position'][1]?>)
	);
<?
	endforeach;
endif;
?>

<?if(!empty($markers)):?>
	
	markerData = [
<?
// generate the marker javascript
$count = 0;

foreach($markers as $marker):
	if($count++ > 0) echo ",";
	
	$lat = $marker['lat'];
	$lon = $marker['lon'];
	
	if(!empty($marker['icon'])) {
		$icon = 'icon: icons["'.$marker['icon']."\"],\n";
		unset($marker['icon']);
	} else {
		$icon = '';
	}
	
	// we don't want lat & lon to be included with the other options
	unset($marker['lat']);
	unset($marker['lon']);
?>
	{
		position: new google.maps.LatLng(<?=$lat?>, <?=$lon?>),
		map:map,
		<?=$icon?>
		<?=substr(json_encode($marker), 1, -1)?>
	}
<?endforeach;?>
	];

	markers = {general:[], all:[]};
	
	for(var a = 0, m; a < markerData.length; a++) {
		m = new google.maps.Marker(markerData[a]);
		
		if(markerData[a]['events']) {
			for(eventName in markerData[a]['events']) {
				google.maps.event.addListener(m, eventName, window[markerData[a]['events'][eventName]]);
			}
		}
		
		if(markerData[a]['category']) {
			if(!markers[markerData[a]['category']]) {
				markers[markerData[a]['category']] = [];
			}
			
			markers[markerData[a]['category']].push(m)
		} else {
			markers['general'].push(m);
		}
		
		markers['all'].push(m);
	}
<?endif;?>
}
