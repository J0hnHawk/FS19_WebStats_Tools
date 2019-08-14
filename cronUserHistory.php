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
$backupPath = '../savegame_backup/'; // Directory for backups
$cacheTimeout = 20000; // Time in seconds until next backup
$retainBackups = 30; // Number of backups to keep before deleting
$logLevel = 2; // 0 = quiet; 1 = default; 2 = extra
               
// -----------------------------------------------------------------------------

$configFile = '../config/webStatsConfig.xml';
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
$online = array ();
foreach ( $stats->Slots->Player as $player ) {
	if ($player ["isUsed"] == "true") {
		$minutesOnline = floor ( $player ["uptime"] );
		$online [$player] = $minutesOnline;
	}
}
$saved = array (
		"Test User" => date ( "Y-m-d H:i", time () - 4 * 60 ) 
);
$log = array (
		0 => array (
				'name' => 'Test User',
				'online' => date ( "Y-m-d H:i", time () - 4 * 60 ) 
		) 
);

foreach ( $saved as $name => $onlineSince ) {
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
