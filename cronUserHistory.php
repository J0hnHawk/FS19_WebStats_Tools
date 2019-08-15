<?php
/**
 * This file is part of the "FS19 Web Stats" package.
 * Copyright (C) 2019 John Hawk <john.hawk@gmx.net> and JanSe <admin@fs19.cz>
 *
 * "FS19 Web Stats" is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * "FS19 Web Stats" is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
ini_set ( 'error_reporting', E_ALL );
ini_set ( 'display_errors', 0 );
ini_set ( 'log_errors', 1 );
ini_set ( 'error_log', 'error.log' );

// -----------------------------------------------------------------------------
// Settings
$logFile = './onlineHistory.xml';
$configFile = '../config/webStatsConfig.xml';
$logLevel = 2; // 0 = quiet; 1 = default; 2 = extra
               
// -----------------------------------------------------------------------------

// Variables
$changeFound = false;
$onlineUser = array ();

function logEntry($level, $entry) {
	global $logLevel;
	$nl = (PHP_SAPI == 'cli') ? "\n" : "<br>\n";
	if ($logLevel >= $level) {
		echo (date ( 'Y-m-d H:i:s - ' ) . $entry . $nl);
	}
}

// Read FS19 Web Stats config file 
logEntry ( 1, "Start user logging." );
if (file_exists ( $configFile )) {
	$webStatsConfig = simplexml_load_file ( $configFile );
	if (! isset ( $webStatsConfig->webStatsVersion )) {
		logEntry ( 1, "Configuration file too old." );
		exit ( 1 );
	}
	if ($webStatsConfig->savegame_type == 'local') {
		logEntry ( 1, "No user logging for local savegames." );
		exit ( 1 );
	}
} else {
	logEntry ( 1, "Configuration file does not exists." );
	exit ( 1 );
}

// Get online user from dedicated server
$ch = curl_init ();
curl_setopt_array ( $ch, array (
		CURLOPT_URL => $webStatsConfig->link_xml,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 10 
) );
$xmlStr = curl_exec ( $ch );
$http_code = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
curl_close ( $ch );
if ($http_code != 200) {
	logEntry ( 1, "Dedicated Server unreachable or code incorrect." );
	exit ( 1 );
}
logEntry ( 1, "Online status downloaded from server." );
$stats = simplexml_load_string ( $xmlStr );
foreach ( $stats->Slots->Player as $player ) {
	if ($player ["isUsed"] == "true") {
		$minutesOnline = floor ( $player ["uptime"] );
		$onlineUser [strval ( $player )] = $minutesOnline;
	}
}

// Read log file or create a new one
if (file_exists ( $logFile )) {
	$logFileXML = simplexml_load_file ( $logFile );
	$storedOnlineUser = $logFileXML->onlineUser;
	$logEnties = $logFileXML->logEntries;
	logEntry ( 2, "Log file loaded." );
} else {
	$logFileXML = new SimpleXMLElement ( '<?xml version="1.0" encoding="UTF-8"?><log></log>' );
	$storedOnlineUser = $logFileXML->addChild ( 'onlineUser' );
	$logEnties = $logFileXML->addChild ( 'logEntries' );
	logEntry ( 2, "New log file created." );
	$changeFound = true;
}

// User still online?
foreach ( $storedOnlineUser->user as $user ) {
	$name = strval ( $user ['name'] );
	if (! isset ( $onlineUser [$name] )) {
		$logEntry = $logEnties->addChild ( 'logEntry' );
		$logEntry->addAttribute ( 'name', $name );
		$logEntry->addAttribute ( 'type', 'offline' );
		$logEntry->addAttribute ( 'since', date ( "Y-m-d H:i", time () ) );
		$dom = dom_import_simplexml ( $user );
		$dom->parentNode->removeChild ( $dom );
		logEntry ( 2, "The user '$name' is no longer online." );
		$changeFound = true;
	} else {
		$lastRunOnlineUser [$name] = strval ( $user ['onlineSince'] );
	}
}

// New user online? 
foreach ( $onlineUser as $name => $minutesOnline ) {
	if (! isset ( $lastRunOnlineUser [$name] )) {
		$onlineSince = date ( "Y-m-d H:i", time () - $minutesOnline * 60 );
		$logEntry = $logEnties->addChild ( 'logEntry' );
		$logEntry->addAttribute ( 'name', $name );
		$logEntry->addAttribute ( 'type', 'online' );
		$logEntry->addAttribute ( 'since', $onlineSince );
		$user = $storedOnlineUser->addChild ( 'user' );
		$user->addAttribute ( 'name', $name );
		$user->addAttribute ( 'onlineSince', $onlineSince );
		logEntry ( 2, "User '$name' is online since: " . $onlineSince );
		$changeFound = true;
	}
}

// Save if changes found
if ($changeFound) {
	$dom = new DOMDocument ( '1.0' );
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML ( $logFileXML->asXML () );
	if ($dom->save ( $logFile )) {
		logEntry ( 1, "Log file saved." );
	} else {
		logEntry ( 1, "Log file could not saved." );
	}
} else {
	logEntry ( 1, "No change found." );
}

