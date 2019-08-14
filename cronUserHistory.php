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
ini_set ( 'display_errors', 1 );
ini_set ( 'log_errors', 1 );
ini_set ( 'error_log', 'error.log' );

// -----------------------------------------------------------------------------
// Settings
$logFile = './onlineHistory.xml/';
$configFile = '../fs19webstats/config/webStatsConfig.xml';
$logLevel = 2; // 0 = quiet; 1 = default; 2 = extra
               
// -----------------------------------------------------------------------------
function logEntry($level, $entry) {
	global $logLevel;
	$nl = (PHP_SAPI == 'cli') ? "\n" : "<br>\n";
	if ($logLevel >= $level) {
		echo (date ( 'Y-m-d H:i:s - ' ) . $entry . $nl);
	}
}
logEntry ( 1, "Start of user history." );
if (file_exists ( $configFile )) {
	$webStatsConfig = simplexml_load_file ( $configFile );
	if (! isset ( $webStatsConfig->webStatsVersion )) {
		logEntry ( 1, "Configuration file too old." );
		exit ( 1 );
	}
	if ($webStatsConfig->savegame_type == 'local') {
		logEntry ( 1, "No user history for local savegames." );
		exit ( 1 );
	}
} else {
	logEntry ( 1, "Configuration file does not exists." );
	exit ( 1 );
}

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
$onlineUser = array ();
foreach ( $stats->Slots->Player as $player ) {
	if ($player ["isUsed"] == "true") {
		$minutesOnline = floor ( $player ["uptime"] );
		$onlineUser [strval ( $player )] = $minutesOnline;
	}
}
if (file_exists ( $logFile )) {
	$logFileXML = simplexml_load_file ( $logFile );
	$lastRunOnlineUser = $logFileXML->onlineUser;
	$logEnties = $logFileXML->logEntries;
} else {
	$logFileXML = new SimpleXMLElement ( '<?xml version="1.0" encoding="UTF-8"?>' );
	$lastRunOnlineUser = $logFileXML->addChild ( 'onlineUser' );
	$logEnties = $logFileXML->addChild ( 'logEntries' );
	// While development
	$user = $lastRunOnlineUser->addChild ( 'user' );
	$user->addAttribute ( 'name', 'MisterX' );
	$user->addAttribute ( 'onlineSince', date ( "Y-m-d H:i", time () - 4 * 60 ) );
	$entry = $logEnties->addChild ( 'entry' );
	$entry->addAttribute ( 'name', 'MisterX' );
	$entry->addAttribute ( 'type', 'online' );
	$entry->addAttribute ( 'since', date ( "Y-m-d H:i", time () - 4 * 60 ) );
	// End while development
}

foreach ( $onlineUser->user as $user ) {
	$lastRunOnlineUser [strval ( $user ['name'] )] = strval ( $user ['onlineSince'] );
}

foreach ( $lastRunOnlineUser as $name => $onlineSince ) {
	if (! isset ( $online [$name] )) {
		$log [] = array (
				'name' => $name,
				'offline' => date ( "Y-m-d H:i", time () ) 
		);
		unset ( $saved [$name] );
	}
}

foreach ( $online as $name => $minutesOnline ) {
	if (! isset ( $saved [$name] )) {
		$onlineSince = date ( "Y-m-d H:i", time () - $minutesOnline * 60 );
		$log [] = array (
				'name' => $name,
				'online' => $onlineSince 
		);
		$saved [$name] = $onlineSince;
	}
}

var_dump ( $saved, $log );
