<?php
$filename = $_SERVER ["SCRIPT_FILENAME"];
$completePath = pathinfo ( $filename );
$topPath = pathinfo ( $completePath ['dirname'] );
$webStatRoot = $topPath ['dirname'];

// Sting for missing translations
$missingTranslation = 'STRING_TO_TRANSLATE';

// If exist, import existing translation
$language = 'de';
$langArray = array ();
if (file_exists ( $webStatRoot . '/language/' . $language . '/global.lng' )) {
	$entries = file ( $webStatRoot . '/language/' . $language . '/global.lng' );
	foreach ( $entries as $row ) {
		if (substr ( ltrim ( $row ), 0, 2 ) == '//' || trim ( $row ) == '') { // ignore comments and emtpty rows
			continue;
		}
		$keyValuePair = explode ( '=', $row );
		$key = trim ( $keyValuePair [0] );
		$value = $keyValuePair [1];
		if (! empty ( $key )) {
			$langArray [$key] = chop ( $value );
		}
	}
}

$allPlaceholders = array ();
$templates = $global = array ();
foreach ( glob ( $webStatRoot . '/templates_c/*.php' ) as $filename ) {
	preg_match ( '/file\.(.*\.tpl)\.php/', $filename, $match );
	$template = $match [1];
	if ($template) {
		$templates [$template] = array ();
		$file = file_get_contents ( $filename );
		preg_match_all ( '/##(.+?)##/', $file, $matches );
		foreach ( $matches [1] as $placeholder ) {
			if (isset ( $allPlaceholders [$placeholder] ) && $allPlaceholders [$placeholder] != $template) {
				$global [$placeholder] = (isset ( $langArray [$placeholder] )) ? $langArray [$placeholder] : $missingTranslation;
				unset ( $templates [$allPlaceholders [$placeholder]] [$placeholder] );
			} else {
				$allPlaceholders [$placeholder] = $template;
				$templates [$template] [$placeholder] = (isset ( $langArray [$placeholder] )) ? $langArray [$placeholder] : $missingTranslation;
			}
		}
		ksort ( $templates [$template] );
	}
}
ksort ( $global );
ksort ( $templates );
$templates = array_reverse ( $templates, true );
$templates ['global'] = $global;
$templates = array_reverse ( $templates, true );
$textarea = '';
foreach ( $templates as $template => $translations ) {
	$textarea .= "//$template\r\n";
	foreach ( $translations as $placeholder => $translation ) {
		$textarea .= "$placeholder=$translation\r\n";
	}
	$textarea .= "\r\n";
}
?>
<html>
<head>
<title>FS19 Web Stats - Site Translation</title>
</head>
<body>
	<p>
		Copy and paste this to you language file (global.lng):
		<textarea style="height: 80%; width: 90%"><?php echo($textarea);?></textarea>
	</p>
</body>
</html>
