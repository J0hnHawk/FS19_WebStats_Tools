<?php
$brandsXML = simplexml_load_file ( './brands.xml' );
$brands = array ();
foreach ( $brandsXML as $brand ) {
	$name = strval ( $brand ['name'] );
	$text = strval ( $brand ['text'] );
	$brands [$name] = $text;
}
$newBrands = false;
$newXML = new SimpleXMLElement ( '<?xml version="1.0" encoding="UTF-8"?><mapconfig></mapconfig>' );
$mapconfig = $newXML->addChild ( 'vehicles' );
foreach ( glob ( "./vehicles/*.xml" ) as $filename ) {
	$vehicle = simplexml_load_file ( $filename );
	$name = strval ( $vehicle->storeData->name );
	if ($name == '') {
		continue;
	}
	$element = $mapconfig->addChild ( 'vehicle' );
	$element->addAttribute ( 'basename', basename ( $filename ) );
	$element->addAttribute ( 'type', strval ( $vehicle ['type'] ) );
	$element->addAttribute ( 'name', $name );
	$brand = strval ( $vehicle->storeData->brand );
	if (isset ( $brands [$brand] )) {
		$brand = $brands [$brand];
	} else {
		$brands [$brand] = $brand;
		$newBrands = true;
	}
	$element->addAttribute ( 'brand', $brand );
	$element->addAttribute ( 'lifetime', strval ( $vehicle->storeData->lifetime ) );
	$element->addAttribute ( 'category', strval ( $vehicle->storeData->category ) );
	$element->addAttribute ( 'wearable', strval ( $vehicle->wearable ['wearDuration'] ) );
	if (isset ( $vehicle->motorized )) {
		$motorId = 1;
		foreach ( $vehicle->motorized->motorConfigurations->motorConfiguration as $motorConfiguration ) {
			$motor = $element->addChild ( 'motor' );
			$motor->addAttribute ( 'id', $motorId );
			$motorConfigurationName = strval ( $motorConfiguration ['name'] );
			if (strlen ( $motorConfigurationName ) < 1) {
				$motorConfigurationName = $name;
			}
			$motor->addAttribute ( 'name', $motorConfigurationName );
			$motorId ++;
		}
	}
}
if ($newBrands) {
	ksort ( $brands );
	$brandsXML = new SimpleXMLElement ( '<?xml version="1.0" encoding="UTF-8"?><brands></brands>' );
	foreach ( $brands as $name => $text ) {
		$element = $brandsXML->addChild ( 'brand' );
		$element->addAttribute ( 'name', $name );
		$element->addAttribute ( 'text', $text );
	}
	$brandsXML->asXML ( './brands.xml' );
}
$newXML->asXML ( './vehicles.xml' );
