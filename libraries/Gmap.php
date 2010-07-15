<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Google Maps API integration.
 *
 * $Id: Gmap.php 4303 2009-05-01 02:50:50Z kiall $
 *
 * @package    Gmaps
 * @author     Kohana Team
 * @copyright  (c) 2007-2008 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Gmap_Core {
	
	// Map settings
	protected $id;
	protected $options;
	protected $center;
	protected $control;
	protected $type_control = FALSE;

	// Map types
	protected $types = array();
	protected $default_types = array('ROADMAP','SATELLITE','HYBRID','TERRAIN');
	
	// Markers icons
	protected $icons = array();

	// Map markers
	protected $markers = array();

	/**
	 * Set the GMap center point.
	 *
	 * @param string $id HTML map id attribute
	 * @param array $options array of GMap constructor options. List of options available here: http://code.google.com/apis/maps/documentation/javascript/reference.html#MapOptions
	 * @return void
	 */
	public function __construct($id = 'map', $options = array()) {
		// set the default center
		$this->center(0, 0);
		
		$this->id = $id;
		$this->options = $options;
		
		/*
		backgroundColor	string	Color used for the background of the Map div. This color will be visible when tiles have not yet loaded as the user pans. This option can only be set when the map is initialized.
		center	LatLng	The initial Map center. Required.
		disableDefaultUI	boolean	Enables/disables all default UI. May be overridden individually.
		disableDoubleClickZoom	boolean	Enables/disables zoom and center on double click. Enabled by default.
		draggable	boolean	If false, prevents the map from being dragged. Dragging is enabled by default.
		draggableCursor	string	The name or url of the cursor to display on a draggable object.
		draggingCursor	string	The name or url of the cursor to display when an object is dragging.
		keyboardShortcuts	boolean	If false, prevents the map from being controlled by the keyboard. Keyboard shortcuts are enabled by default.
		mapTypeControl	boolean	The initial enabled/disabled state of the Map type control.
		mapTypeControlOptions	MapTypeControlOptions	The initial display options for the Map type control.
		mapTypeId	MapTypeId	The initial Map mapTypeId. Required.
		navigationControl	boolean	The initial enabled/disabled state of the navigation control.
		navigationControlOptions	NavigationControlOptions	The initial display options for the navigation control.
		noClear	boolean	If true, do not clear the contents of the Map div.
		scaleControl	boolean	The initial enabled/disabled state of the scale control.
		scaleControlOptions	ScaleControlOptions	The initial display options for the scale control.
		scrollwheel	boolean	If false, disables scrollwheel zooming on the map. The scrollwheel is enabled by default.
		streetView	StreetViewPanorama	A StreetViewPanorama to display when the Street View pegman is dropped on the map. If no panorama is specified, a default StreetViewPanorama will be displayed in the map's div when the pegman is dropped.
		streetViewControl	boolean	The initial enabled/disabled state of the Street View pegman control.
		zoom	Number
		*/
	}

	/**
	* Return GMap javascript url
	*
	* @return  string
	*/
	public static function api_url($type = 'js', $parameters = array()) {
		if($type == 'js') {
			return 'http://'.Kohana::config('gmaps.api_domain').'/maps/api/js?sensor=false';
		} else {
			return 'http://'.Kohana::config('gmaps.api_domain').'/maps/geo?'.http_build_query($parameters);
		}
	}
	
	/**
	 * Retrieves the latitude and longitude of an address.
	 *
	 * @param string $address address
	 * @return array longitude, latitude
	 */
	public static function address_to_ll($address) {
		$lat = NULL;
		$lon = NULL;

		if ($xml = Gmap::address_to_xml($address))
		{
			// Get the latitude and longitude from the Google Maps XML
			// NOTE: the order (lon, lat) is the correct order
			list ($lon, $lat) = explode(',', $xml->Response->Placemark->Point->coordinates);
		}

		return array($lat, $lon);
	}

	/**
	 * Retrieves the XML geocode address lookup.
	 * ! Results of this method are cached for 1 day.
	 *
	 * @param string $address adress
	 * @return object SimpleXML
	 */
	public static function address_to_xml($address) {
		static $cache;

		// Load Cache
		if ($cache === NULL) 
		{
			$cache = Cache::instance();
		}
		
		// Address cache key
		$key = 'gmap-address-'.sha1($address);

		if ($xml = $cache->get($key))
		{
			// Return the cached XML
			return simplexml_load_string($xml);
		}
		else
		{
			// Setup the retry counter and retry delay
			$remaining_retries = Kohana::config('gmaps.retries');
			$retry_delay = Kohana::config('gmaps.retry_delay');

			// Set the XML URL
			$xml_url = Gmap::api_url('xml', array('output' => 'xml', 'q' => $address));

			// Disable error reporting while fetching the feed
			$ER = error_reporting(~E_NOTICE);

			// Enter the request/retry loop.
			while ($remaining_retries)
			{
				// Load the XML
				$xml = simplexml_load_file($xml_url);

				if (is_object($xml) AND ($xml instanceof SimpleXMLElement) AND (int) $xml->Response->Status->code === 200)
				{
					// Cache the XML
					$cache->set($key, $xml->asXML(), array('gmaps'), 86400);

					// Since the geocode was successful, theres no need to try again
					$remaining_retries = 0;
				}
				elseif ((int) $xml->Response->Status->code === 620)
				{
					/* Goole is rate limiting us - either we're making too many requests too fast, or
					 * we've exceeded the 15k per 24hour limit. */

					// Reduce the number of remaining retries
					$remaining_retries--;
					if ( ! $remaining_retries)
				 		return FALSE;

				 	// Sleep for $retry_delay microseconds before trying again.
				 	usleep($retry_delay);
				}
				else
				{
					// Invalid XML response
					$xml = FALSE;

					// Dont retry.
					$remaining_retries = 0;
				}
			}

			// Turn error reporting back on
			error_reporting($ER);
		}

		return $xml;
	}

	/**
	 * Returns an image map
	 *
	 * @param mixed $lat latitude or an array of marker points
	 * @param float $lon longitude
	 * @param integer $zoom zoom level (1-19)
	 * @param string $type map type (roadmap or mobile)
	 * @param integer $width map width
	 * @param integer $height map height
	 * @return string
	 */
	public static function static_map($lat = 0, $lon = 0, $zoom = 6, $type = NULL, $width = 300, $height = 300) {
		// Valid map types
		$types = array('roadmap', 'mobile');

		// Maximum width and height are 640px
		$width = min(640, abs($width));
		$height = min(640, abs($height));

		$parameters['size'] = $width.'x'.$height;

		// Minimum zoom = 0, maximum zoom = 19
		$parameters['zoom'] = max(0, min(19, abs($zoom)));

		if (in_array($type, $types))
		{
			// Set map type
			$parameters['maptype'] = $type;
		}

		if (is_array($lat))
		{
			foreach ($lat as $_lat => $_lon)
			{
				$parameters['markers'][] = $_lat.','.$_lon;
			}

			$parameters['markers'] = implode('|', $parameters['markers']);
		}
		else
		{
			$parameters['center'] = $lat.','.$lon;
		}

		return Gmap::api_url('staticmap', $parameters);
	}

	/**
	 * Set the GMap center point.
	 *
	 * @chainable
	 * @param float $lat latitude
	 * @param float $lon longitude
	 * @param integer $zoom zoom level (1-19)
	 * @param string $type default map type
	 * @return object
	 */
	public function center($lat, $lon, $zoom = 6, $type = 'ROADMAP') {
		$zoom = max(0, min(19, abs($zoom)));
		$type = ($type != 'ROADMAP' AND in_array($type, $this->default_types, true)) ? $type : 'ROADMAP';

		// Set center location, zoom and default map type
		$this->center = array($lat, $lon, $zoom, $type);

		return $this;
	}

	/**
	 * Set the GMap controls size.
	 *
	 * @chainable
	 * @param string $size small or large
	 * @return object
	 */
	public function controls($size = NULL) {
		// Set the control type
		$this->control = (strtolower($size) == 'small') ? 'Small' : 'Large';

		return $this;
	}

	/**
	 * Set the GMap type controls.
	 * by default renders G_NORMAL_MAP, G_SATELLITE_MAP, and G_HYBRID_MAP
	 *
	 * @chainable
	 * @param string $type map type
	 * @param string $action add or remove map type
	 * @return object
	 */
	public function types($type = NULL, $action = 'remove')
	{
		$this->type_control = TRUE;

		if ($type !== NULL AND in_array($type, $this->default_types, true))
		{
			// Set the map type and action
			$this->types[$type] = (strtolower($action) == 'remove') ? 'remove' : 'add';
		}

		return $this;
	}
	
	/**
	 * Create a custom marker icon
	 *
	 * @chainable
	 * @param array $options, should contain two key-value pairs: name, url
	 * @return object
	 */
	public function add_icon($options) {
		// check to make sure we aren't adding the same thing twice
		if(!array_search_key($options['name'], $this->icons, 'name'))
			$this->icons[] = $options;

		return $this;
	}

	/**
	 * Set the GMap marker point.
	 *
	 * @chainable
	 * @param float $lat latitude
	 * @param float $lon longitude
	 * @param string $html HTML for info window
	 * @param array $options marker options
	 * @return object
	 */
	public function add_marker($lat, $lon, $options = array()) {
		$this->markers[] = array_merge(array('lat' => $lat, 'lon' => $lon), $options);

		return $this;
	}

	/**
	 * Render the map into GMap Javascript.
	 *
	 * @param string $template template name
	 * @param array $extra extra fields passed to the template
	 * @return string
	 */
	public function render($template = 'gmaps/javascript') {
		$data = array(
			'id' => $this->id,
			'center' => $this->center,
			'options' => $this->options,
			'icons' => $this->icons,
			'markers' => $this->markers,
		);

		return View::factory($template, $data)->render();
	}
}