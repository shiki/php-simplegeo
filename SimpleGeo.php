<?php

if (!class_exists('OAuthConsumer', false)) // giving a chance for other code possibly already including OAuth.php
	include 'OAuth.php';
if (!class_exists('CURL', false))
	include 'CURL.php';


/**
	Contains a latitude and longitude. Simply represents a coordinate
**/
class GeoPoint {
	public $lat;
	public $lng;
}

/**
	A record object contains data regarding an arbitrary object and the
	layer it resides in
	http://simplegeo.com/docs/getting-started/storage#what-record
	
**/
class GeoRecord {
	
	private $Properties;
	public $Layer;
	public $ID;
	public $Created;
	public $Latitude;
	public $Longitude;
	
	/**
		Create a record with, location is optional if not inserting/updating
		
		@var	string	$layer	The name of the layer
		@var	string	$id		The unique identifier of the record
		
		@param	double	$lat	Latitude
		@param	double	$lng	Longitude
	**/
	public function GeoRecord($layer, $id, $lat = NULL, $lng = NULL) {
		$this->Layer = $layer;
		$this->ID = $id;
		$this->Latitude = $lat;
		$this->Longitude = $lng;
		$this->Properties = array();
		$this->Created = time();
	}
	
	/**
		Returns an array representation of the Record
	**/
	public function ToArray() {
		return array(
			'type' => 'Feature',
			'id' => $this->ID,
			'created' => $this->Created,
			'geometry' => array(
				'type' => 'Point',
				'coordinates' => array($this->Longitude, $this->Latitude),
			),
			'properties' => (object) $this->Properties
		);
	}
	
	
	public function __set($key, $value) {
		$this->Properties[$key] = $value;
	}
	
	public function __get($key) {
		return isset($this->Properties[$key]) ? $this->Properties[$key] : NULL;
	}
	
}

class SimpleGeo extends CURL {

	private $consumer;
	private $token, $secret;
	
	const BASE_URL = 'http://api.simplegeo.com/';
	
	public function SimpleGeo($token = false, $secret = false) {
		$this->token = $token;
		$this->secret = $secret;
		$this->consumer = new OAuthConsumer($this->token, $this->secret);
	}
	
	
/**
		Extracts the ID from a SimpleGeo ID (SG_XXXXXXXXXXXXXXXXXXXXXX)
		
		@param	string	id	The SimpleGeo ID of a feaure
	
	**/
	public static function ExtractID($id) {
		preg_match('~SG_[A-Za-z0-9]{22}~', $id, $matches);
		return isset($matches[0]) ? $matches[0] : false;
	}
	
	/**
		Returns a list of all possible feature categories
		
	**/
	public function FeatureCategories() {
		return $this->SendRequest('GET', '1.0/features/categories.json');
	}
	
	/**
		Returns detailed information of a feature
		
		@var string $handle		Feature ID
	**/
		
	public function Feature($handle) {
		return $this->SendRequest('GET', '1.0/features/' . $handle . '.json');
	}
	
	/**
		Returns context of an IP
		
		@var string $ip			IP Address

	**/
	
	public function ContextIP($ip, $opts = false) {
		return $this->SendRequest('GET', '1.0/context/' . $ip . '.json', $opts);
	}
	
	
	/**
		Returns context of a coordinate
		
		@var mixed $lat			Latitude or GeoPoint
		@var float $lng			Longitude

	**/
	public function ContextCoord($lat, $lng = false, $opts = false) {
		if ($lat instanceof GeoPoint) {
			$lng = $lat->lng;
			$lat = $lat->lat;
			if (is_array($lng)) $opts = $lng;
		}
		return $this->SendRequest('GET', '1.0/context/' . $lat . ',' . $lng . '.json');
	}
	
	
	
	/**
		Returns a specific feature from a coordinate
		
		@var mixed $lat			Latitude or GeoPoint
		@var float $lng			Longitude

	**/
	public function ContextCoordFeature($lat, $lng, $category) {
		if ($lat instanceof GeoPoint) {
			$lng = $lat->lng;
			$lat = $lat->lat;
		}
		return $this->SendRequest('GET', '1.0/context/' . $lat . ',' . $lng . '.json?features__category=' . $category . '&filter=features');
	}
	
	
	
	/**
		Returns context of an address
		
		@var string $address	Human readable address (US only)

	**/
	public function ContextAddress($address, $opts = false) {
		return $this->SendRequest('GET', '1.0/context/address.json', array('address' => $address));
	}
	
	public function WeatherAddress($address) {
		return $this->SendRequest('GET', '1.0/context/address.json', 
			array('address' => $address, 'filter' => "weather")
		);

	}
	
	
	/**
		Returns places nearby an IP
		
		@var string $ip			IP Address
		
		@param string q			Search query
		@param string category	Filter by a classifer (see https://gist.github.com/732639)
		@param float radius		Radius in km (default=25)

	**/
	
	public function PlacesIP($ip, $opts = false) {
		return $this->SendRequest('GET', '1.0/places/' . $ip . '.json', $opts);
	}
	
	
	
	/**
		Returns places nearby a coordinate
		
		@var mixed $lat			Latitude or GeoPoint
		@var float $lng			Longitude
		
		
		@param string q			Search query
		@param string category	Filter by a classifer (see https://gist.github.com/732639)
		@param float radius		Radius in km (default=25)

	**/
	public function PlacesCoord($lat, $lng = false, $opts = false) {
		if ($lat instanceof GeoPoint) {
			if (is_array($lng)) $opts = $lng;
			$lng = $lat->lng;
			$lat = $lat->lat;
		}
		return $this->SendRequest('GET', '1.2/places/' . $lat . ',' . $lng . '.json', $opts);
	}
	
	
	
	/**
		Returns places nearby an address
		
		@var string $address	Human readable address (US only)
		
		@param string q			Search query
		@param string category	Filter by a classifer (see https://gist.github.com/732639)
		@param float radius		Radius in km (default=25)

	**/
	public function PlacesAddress($address, $opts = array()) {
		return $this->SendRequest('GET', '1.0/places/address.json', array_merge(array(
			'address' => $address
		), $opts));
	}

	/**
		Searches a place's name, categories, phone number, address, city, state, and country.
		
		@var string $searchText
		
		@param string category	Filter by a classifer (see https://gist.github.com/732639)
		@param int limit The number of results to return (1-100). The default is 25.
		@param int start The first place that should be returned, e.g. start=50 would return places 
		                 starting with the 50th result. Useful for pagination.

	**/
	public function PlacesSearch($searchText, $opts = false) {
		if (!is_array($opts))
			$opts = array();
		$opts['q'] = $searchText;
		return $this->SendRequest('GET', '1.2/places/search.json', $opts);
	}
	
	
	/**
		Use the Places endpoint to contribute a new feature to SimpleGeo Places. Each new feature 
		gets a unique, consistent handle; if you insert the same record twice, the more recent 
		insert overwrites the previous one.
		
		When you add a feature, it is visible only to your application until approved for general 
		use. To keep your features private to your application, include "private": true in the GeoJSON 
		properties.
	
		@var Feature $place	A place object to insert
		
	**/
	public function CreatePlace(Place $place) {
		$context = $this->ContextCoord($place->Latitude, $place->Longitude);
		return $this->SendRequest('POST', '1.0/places', json_encode($place->ToArray()));
	}
	
	
	/**
		Update a place
	
		@var Feature $place	A place object to modify
		
	**/
	public function UpdatePlace(Place $place) {
		return $this->SendRequest('POST', '1.0/features/' . $place->ID . '.json', json_encode($place->ToArray()));
	}
	
	
	/**
		Suggests that a Feature be deleted and effectively hides it from your view. Requires a handle.
		Returns a status token.
		
		@var Feature $place	The place to delete
	
	**/
	public function DeletePlace(Place $place) {
		return $this->SendRequest('DELETE', '1.0/features/' . $place->ID . '.json');
	}

	/**
		Gets a place based on a given id.

		@var string $id The id of the place
	**/
	public function GetPlace($id) {
		return $this->SendRequest('GET', '1.2/places/' . $id . '.json');
	}
	

	/**
		Inserts a record according to the ID and Layer properties of the SimpleGeo Storage record
		
		@var	Record	$record	The record to insert
		
	**/
	public function PutRecord(GeoRecord $record) {
		return $this->SendRequest('PUT', '0.1/records/' . $record->Layer . '/' . $record->ID . '.json', json_encode($record->ToArray()));
	}
	
	
	
	/**
		Gets a record according to the ID and Layer properties of the SimpleGeo Storage record
		
		@var	Record	$record	The record to retrieve
		
	**/
	public function GetRecord(GeoRecord $record) {
		return $this->SendRequest('GET', '0.1/records/' . $record->Layer . '/' . $record->ID . '.json');
	}
	
	
	/**
		Delete a record according to the ID and Layer properties of the SimpleGeo Storage record
		
		@var	Record	$record	The record to delete
		
	**/
	public function DeleteRecord(GeoRecord $record) {
		return $this->SendRequest('DELETE', '0.1/records/' . $record->Layer . '/' . $record->ID . '.json');
	}
	
	
	
	/**
		Retrieve the history of an individual Simplegeo Storage record
		
		@var	Record	$record	The record to retrieve the history of
	**/
	public function RecordHistory(GeoRecord $record) {
		return $this->SendRequest('GET', '0.1/records/' . $record->Layer . '/' . $record->ID . '/history.json');
	}
	
	
	
	/**
		Retrieve SimpleGeo Storage records nearby the coordinate given
		
		@var	string	$layer	The name of the layer to retrieve records from
		@var	double	$lat	Latitude
		@var	double	$lat	Longitude
		
		@params	array	$params	Additional parameters in an associate array (radius, limit, types, start, end)
		
	**/
	public function NearbyRecordsCoord($layer, $lat, $lng, $params = array()) {
		return $this->SendRequest('GET', '0.1/records/' . $layer . '/nearby/' . $lat . ',' . $lng . '.json', $params);
	}
	
	
	/**
		Returns nearby SimpleGeo Storage records near a street address
		
		@var string $address	Human readable address (US only)
		
		@params	array	$params	Additional parameters in an associate array (radius, limit, types, start, end)

	**/
	public function NearbyRecordsAddress($layer, $address, $params = array()) {
		return $this->SendRequest('GET', '0.1/records/' . $layer . '/nearby/address.json', array_merge(array(
			'address' => $address
		), $params));
	}
	
	
	/**
		Retrieve SimpleGeo Storage records nearby a geohash
		
		@var	string	$layer	The name of the layer to retrieve records from
		@var	string	$hash	The geohash (see geohash.org) of the location
		
		@params	array	$params	Additional parameters in an associate array (radius, limit, types, start, end)
		
	**/
	public function NearbyRecordsGeohash($layer, $hash, $params = array()) {
		return $this->SendRequest('GET', '0.1/records/' . $layer . '/nearby/' . $hash . '.json', $params);
	}
	
	
	
	/**
		Retrieve SimpleGeo Storage records near an IP address
		
		@var	string	$layer	The name of the layer to retrieve records from
		@var	string	$ip		The IP address to search around
		
		@params	array	$params	Additional parameters in an associate array (radius, limit, types, start, end)
		
	**/
	public function NearbyRecordsIP($layer, $ip, $params = array()) {
		return $this->SendRequest('GET', '0.1/records/' . $layer . '/nearby/' . $ip . '.json', $params);
	}

	/**
		Retrieve all layers you own.

		@params	array	$params	Additional parameters in an associate array (limit, cursor)
		
	**/
	public function GetLayers($params = array()) {
		return $this->SendRequest('GET', '0.1/layers.json', $params);
	}

	/**
		Retrieve details of a layer.

		@var	string	$layer	The name of the layer

	**/
	public function GetLayer($layer) {
		return $this->SendRequest('GET', '0.1/layers/' . $layer . '.json');
	}

	/**
		Create or update a layer.

		@var	string	$layer	The name of the layer to create or update
		@var	string	$title	
		@var	string	$description
		@var	boolean	$public	

	**/
	public function PutLayer($layer, $title, $description, $public = false) {
		$params = array(
			'title' => $title,
			'description' => $description,
			'public' => $public,
		);
		return $this->SendRequest('PUT', '0.1/layers/' . $layer . '.json', json_encode($params));
	}
	
	/**
		Delete a layer

		@var	string	$layer	The name of the layer to delete

	**/
	public function DeleteLayer($layer) {
		return $this->SendRequest('DELETE', '0.1/layers/' . $layer . '.json');
	}
	
	/**
		Include the OAuthHeader in the request
		
	**/
	private function IncludeAuthHeader() {
		$request = OAuthRequest::from_consumer_and_token($this->consumer, NULL, $this->Method, $this->GetURL(), NULL);
		$request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $this->consumer, NULL);
		$this->SetHeader('Authorization', $request->to_header(true, false));
	}
	
	/**
		Take the results and return a JSON decoded version
		
	**/
	public function GetResults() {
		return json_decode($this->Data, true);
	}
	
	
	private function SendRequest($method = 'GET', $endpoint, $data = array()) {
		$this->Revert(self::BASE_URL . $endpoint);
		$this->SetMethod($method);
		if (is_array($data)) $this->AddVars($data);
		else if (is_string($data)) $this->SetBody($data);
		$this->IncludeAuthHeader();
		return $this->Get();
	}
}

/*

(The MIT License)

Copyright (c) 2011 Rishi Ishairzay &lt;rishi [at] ishairzay [dot] com&gt;

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
'Software'), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/


?>
