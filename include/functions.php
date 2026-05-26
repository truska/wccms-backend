<?php
/**
 * Get icon code
 * 
 * This function is used to get the icon code
 * 
 * @param string $name1 The name of the icon
 * @return string The icon code
 */
function getIcon($name1)
{
	$icon = cms_db_fetch_one("SELECT * FROM `icons` WHERE `name` = :name", [
		':name' => $name1,
	]) ?? [];

	$code = $icon["code"] ?? '';

	return  $code;
}

/**
 * Get icon title
 * 
 * This function is used to get the icon title
 * 
 * @param string $name2 The name of the icon
 * @return string The icon title
 */
function getIconTitle($name2)
{
	$icon = cms_db_fetch_one("SELECT * FROM `icons` WHERE `name` = :name", [
		':name' => $name2,
	]) ?? [];

	$title = $icon["title"] ?? '';

	return  $title;
}

/**
 * Get icon background colour
 * 
 * This function is used to get the icon background colour
 * 
 * @param string $name3 The name of the icon
 * @return string The icon background colour
 */
function getIconBgColour($name3)
{
	$icon = cms_db_fetch_one("SELECT * FROM `icons` WHERE `name` = :name", [
		':name' => $name3,
	]) ?? [];

	$color = $icon["colour"] ?? '';

	return  $color;
}

/**
 * Get icon text colour
 * 
 * This function is used to get the icon text colour
 * 
 * @param string $name4 The name of the icon
 * @return string The icon text colour
 */
function getIconTextColour($name4)
{
	$icon = cms_db_fetch_one("SELECT * FROM `icons` WHERE `name` = :name", [
		':name' => $name4,
	]) ?? [];

	$textcolor = $icon["textcolour"] ?? '';

	return  $textcolor;
}

//pref function 

/**
 * Load preferences
 * 
 * This function is used to load the preferences from the database
 * 
 * @return array The preferences
 */
function loadPrefs()
{
	$icon = cms_db_fetch_all("SELECT `name`,`value` FROM `cms_preferences` ORDER BY `prefCat`");

	$prefs = [];
	foreach ($icon as $rowprefs) {
		$prefs[$rowprefs["name"]] = $rowprefs["value"];
	}

	return $prefs;
}

/**
 * Load Shop preferences
 * 
 * This function is used to load the shop preferences from the database
 * 
 * @return array The shop preferences
 */
function loadShopPrefs()
{
	$queryshopprefs = cms_db_fetch_all("SELECT `name`, `value` FROM `preferences_shop` ORDER BY `prefCat`");

	$prefshop = [];
	foreach ($queryshopprefs as $rowshopprefs) {
		$prefshop[$rowshopprefs["name"]] = $rowshopprefs["value"];
	}

	return $prefshop;
}

function getCompanyName($prefs)
{
	return $prefs['prefCompanyName'];
}
function getSiteName($prefs)
{
	return $prefs['prefSiteName'];
}
function getEmail($prefs)
{
	return $prefs['prefEmail'];
}
function getTel1($prefs)
{
	return $prefs['prefTel1'];
}
function getTel1Int($prefs)
{
	return $prefs['prefTel1Int'];
}
function getTel2($prefs)
{
	return $prefs['prefTel2'];
}
function getTel2Int($prefs)
{
	return $prefs['prefTel2Int'];
}
function getFax($prefs)
{
	return $prefs['prefFax'];
}
function getLogo($prefs)
{
	return  $prefs['prefLogo'];
}
function getTagline($prefs)
{
	return $prefs['prefTagline'];
}

// End loadprefs specifics

/**
 * Security check function
 * 
 * This function is used to check the security of the parameters passed to the function
 * 
 * @param string $param The parameter to be checked
 * @param string $type The type of the parameter
 * @return string|boolean The parameter if it is safe, false otherwise
 */
function securityCheck($param, $type = null)
{
	switch ($type) {
		case 'number':
			if (is_numeric($param)) {
				return trim((string) $param);
			} else {
				return false;
			}
			break;
		default:
			return trim((string) $param);
			break;
	}
}


/**
 * Function to sanitize a filename
 * 
 * This function is used to sanitize a filename
 * 
 * @param string $filename The filename to be sanitized
 * @return string The sanitized filename
 */
function sanitizeName($filename)
{
	$name = strtolower($filename);
	$name = preg_replace("/[^a-zA-Z0-9_\.]/", "", $name); // remove special characters
	$name = preg_replace("/[\s_]/", "-", $name); // replace spaces and underscores with dashes
	$name = preg_replace("/-+/", "-", $name); // remove multiple dashes
	$name = preg_replace("/^-/", "", $name); // remove leading dash
	$name = preg_replace("/-$/", "", $name); // remove trailing dash
	return $name;
}

/**
 * Function to upload a file
 * 
 * This function is used to upload a file
 * 
 * @param string $file The file to be uploaded
 * @param string $folder The folder where the file will be uploaded
 * @param string $name The name of the file
 * @return array The response of the upload
 */
function uploadFile($file, $folder, $name)
{
	$response = [];
	$destination_path = $folder . "/" . $name;

	if (move_uploaded_file($file, $destination_path)) {
		$response['status'] = 'success';
		$response['message'] = "File uploaded successfully";
	} else {
		$errors['status'] = 'error';
		$errors['message'] = "Error uploading file";
		$errors['filename'] = $name;
		$errors['folder'] = $folder;
	}

	return $response;
}

function replaceURL($url, $frm, $id, $show = null)
{
	$url = str_replace("[frm]", $frm, $url);
	$url = str_replace("[id]", $id, $url);
	if ($show != null) {
		$url = str_replace("[show]", $show, $url);
	}

	return $url;
}

function replaceURLv2($url, $params)
{
	foreach ($params as $key => $value) {
		$url = str_replace("[$key]", $value, $url);
	}

	return $url;
}

	function calculateTitlePixelWidth($title) {
		// Approximate pixel widths for Arial Bold (Google's font for titles)
		$charWidths = [
			'W' => 12, 'M' => 11, 'I' => 7, 'i' => 4, 'l' => 5, 'm' => 10, 'w' => 10,
			'A' => 9, 'B' => 9, 'C' => 9, 'D' => 10, 'E' => 8, 'F' => 8, 'G' => 10, 'H' => 10,
			'J' => 7, 'K' => 9, 'L' => 8, 'N' => 10, 'O' => 10, 'P' => 9, 'Q' => 10, 'R' => 9,
			'S' => 9, 'T' => 8, 'U' => 10, 'V' => 9, 'X' => 9, 'Y' => 9, 'Z' => 9,
			'a' => 8, 'b' => 8, 'c' => 7, 'd' => 8, 'e' => 8, 'f' => 5, 'g' => 8, 'h' => 8,
			'j' => 4, 'k' => 8, 'n' => 8, 'o' => 9, 'p' => 8, 'q' => 8, 'r' => 6, 's' => 7,
			't' => 5, 'u' => 8, 'v' => 8, 'x' => 8, 'y' => 8, 'z' => 7,
			'0' => 9, '1' => 6, '2' => 9, '3' => 9, '4' => 9, '5' => 9, '6' => 9, '7' => 9,
			'8' => 9, '9' => 9,
			' ' => 4, '-' => 5, '.' => 4, ',' => 4, ':' => 4, '_' => 6, '/' => 6, '|' => 4
		];
		
		// Calculate total width of the title
		$pixelWidth = 0;
		foreach (str_split($title) as $char) {
			$pixelWidth += $charWidths[$char] ?? 8; // Default 8px if unknown
		}
		
		return $pixelWidth;
	}
?>
