<?
list($lat, $lon, $zoom, $type) = $center;
?>
function gmap_init() {
	var map = new google.maps.Map(document.getElementById('<?=$id?>'), {
		zoom: <?=$zoom?>,
		center: new google.maps.LatLng(<?=$lat?>, <?=$lon?>),
		mapTypeId: google.maps.MapTypeId.<?=$type?>,
		<?=substr(json_encode($options), 1, -1)?> 
	});
}

window.onload = gmap_init;
