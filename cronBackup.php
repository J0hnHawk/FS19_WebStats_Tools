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
logEntry ( 1, "Start of savegame backup." );
if (file_exists ( $configFile )) {
	$webStatsConfig = simplexml_load_file ( $configFile );
	if (! isset ( $webStatsConfig->webStatsVersion )) {
		logEntry ( 1, "Configuration file too old. End of savegame backup." );
		exit ( 1 );
	}
	if ($webStatsConfig->savegame_type == 'local') {
		logEntry ( 1, "No backup for local savegames. End of savegame backup." );
		exit ( 1 );
	}
} else {
	logEntry ( 1, "Configuration file does not exists. End of savegame backup." );
	exit ( 1 );
}
if (! file_exists ( $backupPath )) {
	if (mkdir ( $backupPath )) {
		logEntry ( 2, "Directory for backups created." );
	} else {
		logEntry ( 1, "Directory for backups could not created. End of savegame backup." );
		exit ( 1 );
	}
}
// Find last backup file
$backupFiles = array ();
$lastBackupFile = $newBackupFile = 'backup_' . $webStatsConfig->savegame_slot . date ( '_Ymd_Hi' ) . '.zip';
foreach ( glob ( $backupPath . 'backup_' . $webStatsConfig->savegame_slot . '*.zip' ) as $filename ) {
	$backupFiles [filemtime ( $filename )] = $filename;
}
if (is_array ( $backupFiles )) {
	ksort ( $backupFiles );
	$numberBackups = sizeof ( $backupFiles );
	logEntry ( 2, "Number of existing backups: " . $numberBackups );
	if ($numberBackups >= $retainBackups) {
		for($i = 0; $i <= ($numberBackups - $retainBackups); $i ++) {
			$filename = array_shift ( $backupFiles );
			if (@unlink ( $filename )) {
				logEntry ( 2, "Deleting: " . $filename );
			} else {
				logEntry ( 1, $filename . ' could not deleted' );
			}
		}
	}
	$lastBackupFile = end ( $backupFiles );
}
logEntry ( 1, $newBackupFile );
if (file_exists ( $lastBackupFile ) && filemtime ( $lastBackupFile ) > (time () - ($cacheTimeout) + rand ( 0, 10 ))) {
	logEntry ( 1, "Nothing to do. End of savegame backup." );
	exit ( 0 );
} else {
	$ch = curl_init ();
	$postData = array (
			'username' => $webStatsConfig->webinterface_username,
			'password' => $webStatsConfig->webinterface_password,
			'login' => 'Anmelden' 
	);
	curl_setopt_array ( $ch, array (
			CURLOPT_URL => $webStatsConfig->webinterface_url . 'index.html',
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $postData,
			CURLOPT_COOKIEJAR => $backupPath . 'cookie.txt',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 10 
	) );
	$response = curl_exec ( $ch );
	if (curl_getinfo ( $ch, CURLINFO_HTTP_CODE ) != 200 || strpos ( $response, 'Username or password incorrect' ) !== false) {
		logEntry ( 1, "Dedicated Server unreachable or login impossible. End of savegame backup." );
		exit ( 1 );
	} else {
		curl_setopt_array ( $ch, array (
				CURLOPT_URL => $webStatsConfig->webinterface_url . $webStatsConfig->savegame_slot,
				CURLOPT_POST => false 
		) );
		$savegame = curl_exec ( $ch );
		file_put_contents ( $newBackupFile, $savegame );
		curl_setopt ( $ch, CURLOPT_URL, $webStatsConfig->webinterface_url . 'index.html?logout=true' );
		$store = curl_exec ( $ch );
		curl_close ( $ch );
		if (strlen ( $newBackupFile ) > 100) {
			logEntry ( 1, "Backup done. End of savegame backup." );
			exit ( 0 );
		} else {
			logEntry ( 1, "Savegame does not exists. End of savegame backup." );
			exit ( 1 );
		}
	}
}
function logEntry($level, $entry) {
	global $logLevel;
	$nl = (PHP_SAPI == 'cli') ? "\n" : "<br>\n";
	if ($logLevel >= $level) {
		echo (date ( 'Y-m-d H:i:s - ' ) . $entry . $nl);
	}
}