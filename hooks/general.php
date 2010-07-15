<?
function array_search_key($needle, $haystack, $haystackKey, $strict = false) {
	foreach($haystack as $key => $value) {
		if(isset($value[$haystackKey])) {
			if($strict) {
				if($value[$haystackKey] === $needle) return true;
			} else {
				if($value[$haystackKey] == $needle) return true;
			}
		}
	}
	
	return false;
}
?>