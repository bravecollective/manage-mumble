<?php if (!defined('GUEST')) die('go away'); ?>

<?php

function db_exec($stm, $code, $msg) {
    global $_SESSION;

    if (!$stm->execute()) {
	$_SESSION['error_code'] = $code;
	$_SESSION['error_message'] = $msg;
	$arr = $stm->ErrorInfo();
	error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	return false;
    }

    return true;
}

function krand($length) {
    $alphabet = "abcdefghkmnpqrstuvwxyzABCDEFGHKMNPQRSTUVWXYZ23456789";
    $pass = "";
    for($i = 0; $i < $length; $i++) {
	$pass = $pass . substr($alphabet, hexdec(bin2hex(openssl_random_pseudo_bytes(1))) % strlen($alphabet), 1);
    }
    return $pass;
}

function sstart() {
    global $_SESSION;

    session_start();

    if (!isset($_SESSION['nonce'])) {
	$_SESSION['nonce'] = krand(22);
    }
}

function sdestroy() {
    global $_SESSION;

    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

function toMumbleName($name) {
    return strtolower(preg_replace("/[^A-Za-z0-9\-]/", '_', $name));
}

function sso_update() {
    global $cfg_ccp_client_id, $cfg_ccp_client_secret, $cfg_user_agent, $cfg_sql_url, $cfg_sql_user, $cfg_sql_pass, $cfg_restrict_access_by_ticker, $_SESSION, $_GET;

    // ---- Check parameters

    if (!isset($_GET['state'])) {
	$_SESSION['error_code'] = 10;
	$_SESSION['error_message'] = 'State not found.';
	return false;
    }
    $sso_state = $_GET['state'];

    if (!isset($_GET['code'])) {
	$_SESSION['error_code'] = 11;
	$_SESSION['error_message'] = 'Code not found.';
	return false;
    }
    $sso_code = $_GET['code'];

    if (!isset($_SESSION['nonce'])) {
	$_SESSION['error_code'] = 12;
	$_SESSION['error_message'] = 'Nonce not found.';
	return false;
    }
    $nonce = $_SESSION['nonce'];

    // ---- Verify nonce

    if ($nonce != $sso_state) {
	$_SESSION['error_code'] = 20;
	$_SESSION['error_message'] = 'Nonce is out of sync.';
	return false;
    }

    // ---- Translate code to token

    $data = http_build_query(
	array(
	    'grant_type' => 'authorization_code',
	    'code' => $sso_code,
	)
    );
    $options = array(
	'http' => array(
	    'method'  => 'POST',
	    'header'  => array(
		'Authorization: Basic ' . base64_encode($cfg_ccp_client_id . ':' . $cfg_ccp_client_secret),
		'Content-type: application/x-www-form-urlencoded',
		'Host: login.eveonline.com',
		'User-Agent: ' . $cfg_user_agent,
	    ),
	    'content' => $data,
        ),
    );
    $result = file_get_contents('https://login.eveonline.com/oauth/token', false, stream_context_create($options));

    if (!$result) {
	$_SESSION['error_code'] = 30;
	$_SESSION['error_message'] = 'Failed to convert code to token.';
	return false;
    }
    $sso_token = json_decode($result)->access_token;

    // ---- Translate token to character

    $options = array(
	'http' => array(
	    'method'  => 'GET',
	    'header'  => array(
		'Authorization: Bearer ' . $sso_token,
		'Host: login.eveonline.com',
		'User-Agent: ' . $cfg_user_agent,
	    ),
        ),
    );
    $result = file_get_contents('https://login.eveonline.com/oauth/verify', false, stream_context_create($options));

    if (!$result) {
	$_SESSION['error_code'] = 40;
	$_SESSION['error_message'] = 'Failed to convert token to character.';
	return false;
    }

    $json = json_decode($result);
    $character_id = $json->CharacterID;
    $owner_hash = $json->CharacterOwnerHash;

    // ---- Database

    try {
	$dbr = new PDO($cfg_sql_url, $cfg_sql_user, $cfg_sql_pass);
    } catch (PDOException $e) {
	$_SESSION['error_code'] = 50;
	$_SESSION['error_message'] = 'Failed to connect to the database.';
	return false;
    }

    // ---- Update user

    if (!update_user($dbr, $character_id, $owner_hash)) {
	return false;
    }

    // ---- Success

    $_SESSION['error_code'] = 0;
    $_SESSION['error_message'] = 'OK';

    return true;
}

function update_pass() {
    global $cfg_sql_url, $cfg_sql_user, $cfg_sql_pass, $_SESSION, $_GET;

    // ---- Verify access

    if (!isset($_SESSION['character_id']) || $_SESSION['character_id'] == 0) {
	$_SESSION['error_code'] = 100;
	$_SESSION['error_message'] = 'User not found.';
	return false;
    }
    $character_id = $_SESSION['character_id'];

    if (!isset($_SESSION['nonce'])) {
	$_SESSION['error_code'] = 101;
	$_SESSION['error_message'] = 'Nonce not found in session.';
	return false;
    }
    if (!isset($_GET['n'])) {
	$_SESSION['error_code'] = 102;
	$_SESSION['error_message'] = 'Nonce not found in url.';
	return false;
    }

    if ($_SESSION['nonce'] != $_GET['n']) {
	$_SESSION['error_code'] = 103;
	$_SESSION['error_message'] = 'Nonces dont match.';
	return false;
    }

    // ---- Database

    try {
	$dbr = new PDO($cfg_sql_url, $cfg_sql_user, $cfg_sql_pass);
    } catch (PDOException $e) {
	$_SESSION['error_code'] = 104;
	$_SESSION['error_message'] = 'Failed to connect to the database.';
	return false;
    }

    // ---- Update user

    $mumble_password = krand(10);

    $stm = $dbr->prepare('UPDATE user SET mumble_password = :mumble_password WHERE character_id = :character_id');
    $stm->bindValue(':character_id', $character_id);
    $stm->bindValue(':mumble_password', $mumble_password);

    if (!db_exec($stm, 105, 'Failed to update password.')) {
	return false;
    }

    if (!$stm->rowCount() > 0) {
	    $_SESSION['error_code'] = 106;
	    $_SESSION['error_message'] = 'Unknown user for password update.';
	    return false;
    }

    // ---- Success

    $_SESSION['error_code'] = 0;
    $_SESSION['error_message'] = 'OK';

    $_SESSION['mumble_password'] = $mumble_password;

    return true;
}

function update_user($dbr, $character_id, $owner_hash) {
    global $cfg_user_agent, $cfg_core_host, $cfg_core_secret, $_SESSION;

    $ts_now = time();
    $ts_min = $ts_now - 20;

    //$character_id from parameter
    $character_name = NULL;

    $mumble_username = NULL;
    $mumble_password = NULL;

    //$owner_hash from parameter

    $corporation_id = 0;
    $corporation_name = NULL;
    $corporation_ticker = '-----';

    $faction_id = 0;
    $faction_name = NULL;

    $alliance_id = 0;
    $alliance_name = NULL;
    $alliance_ticker = '-----';

    $tags = NULL;
    $groups = NULL;
    $perms = NULL;

// ----- Update character

    $stm = $dbr->prepare('SELECT * FROM user WHERE character_id = :character_id');
    $stm->bindValue(':character_id', $character_id);
    if (!db_exec($stm, 201, 'Failed to query character.')) {
	return false;
    }

    $outdated = true;

    if ($row = $stm->fetch()) {
	$character_name = $row['character_name'];
	$corporation_id = $row['corporation_id'];
	$mumble_username = $row['mumble_username'];
	$mumble_password = $row['mumble_password'];
	$outdated = $row['updated_at'] < $ts_min;
    }

    if (!$row or $outdated) {
	$options = array(
	    'http' => array(
		'method'  => 'GET',
		'header'  => array(
		    'Host: api.eveonline.com',
		    'User-Agent: ' . $cfg_user_agent,
		),
	    ),
	);
	$result = file_get_contents('https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=' . $character_id, false, stream_context_create($options));
	if ($result) {
	    $apiInfo = new SimpleXMLElement($result);
	    $api = $apiInfo->result->rowset->row->attributes();
	    $character_name = (string)$api->characterName;
	    $corporation_id = (int)$api->corporationID;
	    $mumble_username = toMumbleName($character_name);

	    $stm = NULL;

	    if (!$row) {
		$mumble_password = krand(10);
		$stm = $dbr->prepare('INSERT INTO user (character_id, character_name, corporation_id, mumble_username, mumble_password, owner_hash, updated_at) VALUES (:character_id, :character_name, :corporation_id, :mumble_username, :mumble_password, :owner_hash, :updated_at)');
		$stm->bindValue(':mumble_password', $mumble_password);
	    } else {
		$stm = $dbr->prepare('UPDATE user set character_name = :character_name, corporation_id = :corporation_id, mumble_username = :mumble_username, owner_hash = :owner_hash, updated_at = :updated_at WHERE character_id = :character_id');
	    }
	    $stm->bindValue(':character_id', $character_id);
	    $stm->bindValue(':character_name', $character_name);
	    $stm->bindValue(':corporation_id', $corporation_id);
	    $stm->bindValue(':mumble_username', $mumble_username);
	    $stm->bindValue(':owner_hash', $owner_hash);
	    $stm->bindValue(':updated_at', $ts_now);
	    if (!db_exec($stm, 202, 'Failed to insert or update character.')) {
		return false;
	    }
	} elseif (!$row) {
	    $_SESSION['error_code'] = 200;
	    $_SESSION['error_message'] = 'Failed to retrieve character details.';
	    return false;
	}
    }

// ----- Update corporation

    $stm = $dbr->prepare('SELECT * FROM corporation WHERE corporation_id = :corporation_id');
    $stm->bindValue(':corporation_id', $corporation_id);
    if (!db_exec($stm, 201, 'Failed to query corporation.')) {
	return false;
    }

    $outdated = true;

    if ($row = $stm->fetch()) {
	$corporation_name = $row['corporation_name'];
	$corporation_ticker = $row['corporation_ticker'];
	$alliance_id = $row['alliance_id'];
	$faction_id = $row['faction_id'];
	$faction_name = $row['faction_name'];
	$outdated = $row['updated_at'] < $ts_min;
    }

    if (!$row or $outdated) {
	$options = array(
	    'http' => array(
		'method'  => 'GET',
		'header'  => array(
		    'Host: api.eveonline.com',
		    'User-Agent: ' . $cfg_user_agent,
		),
	    ),
	);
	$result = file_get_contents('https://api.eveonline.com/corp/CorporationSheet.xml.aspx?corporationID=' . $corporation_id, false, stream_context_create($options));
	if ($result) {
	    $apiInfo = new SimpleXMLElement($result);
	    $api = $apiInfo->result;
	    $corporation_name = (string)$api->corporationName;
	    $corporation_ticker = (string)$api->ticker;
	    $alliance_id = (int)$api->allianceID;
	    $faction_id = (int)$api->factionID;
	    $faction_name = (string)$api->factionName;

	    if (!$row) {
		$stm = $dbr->prepare('INSERT INTO corporation (corporation_id, corporation_name, corporation_ticker, alliance_id, faction_id, faction_name, updated_at) VALUES (:corporation_id, :corporation_name, :corporation_ticker, :alliance_id, :faction_id, :faction_name, :updated_at)');
	    } else {
		$stm = $dbr->prepare('UPDATE corporation set corporation_name = :corporation_name, corporation_ticker = :corporation_ticker, alliance_id = :alliance_id, faction_id = :faction_id, faction_name = :faction_name, updated_at = :updated_at WHERE corporation_id = :corporation_id');
	    }

	    $stm->bindValue(':corporation_id', $corporation_id);
	    $stm->bindValue(':corporation_name', $corporation_name);
	    $stm->bindValue(':corporation_ticker', $corporation_ticker);

	    $stm->bindValue(':alliance_id', $alliance_id);
	    $stm->bindValue(':faction_id', $faction_id);
	    $stm->bindValue(':faction_name', $faction_name);
	    $stm->bindValue(':updated_at', $ts_now);
	    if (!db_exec($stm, 202, 'Failed to insert or update corporation.')) {
		return false;
	    }
	} elseif (!$row) {
	    $_SESSION['error_code'] = 200;
	    $_SESSION['error_message'] = 'Failed to retrieve corporation details.';
	    return false;
	}
    }

// ----- Update core


    $stm = $dbr->prepare('SELECT * FROM core WHERE character_id = :character_id');
    $stm->bindValue(':character_id', $character_id);
    if (!db_exec($stm, 201, 'Failed to query core.')) {
	return false;
    }

    $outdated = true;

    if ($row = $stm->fetch()) {
	$tags = $row['tags'];
	$groups = $row['groups'];
	$perms = $row['perms'];
	$outdated = $row['updated_at'] < $ts_min;
    }

    if (!$row or $outdated) {
	$options = array(
	    'http' => array(
		'method'  => 'GET',
		'header'  => array(
		    'Host: ' . $cfg_core_host,
		    'User-Agent: ' . $cfg_user_agent,
		),
	    ),
	);
	$result = file_get_contents('https://' . $cfg_core_host . '/kiu?charid=' . $character_id . '&secret=' . $cfg_core_secret, false, stream_context_create($options));
	if ($result) {
	    $json = json_decode($result);
	    $tags = implode(', ', $json->tags);
	    $groups = implode(', ', $json->groups);
	    $perms = implode(', ', $json->perms);

	    if (!$row) {
		$stm = $dbr->prepare('INSERT INTO core (character_id, tags, groups, perms, updated_at) VALUES (:character_id, :tags, :groups, :perms, :updated_at)');
	    } else {
		$stm = $dbr->prepare('UPDATE core set tags = :tags, groups = :groups, perms = :perms, updated_at = :updated_at WHERE character_id = :character_id');
	    }

	    $stm->bindValue(':character_id', $character_id);
	    $stm->bindValue(':tags', $tags);
	    $stm->bindValue(':groups', $groups);
	    $stm->bindValue(':perms', $perms);
	    $stm->bindValue(':updated_at', $ts_now);
	    if (!db_exec($stm, 202, 'Failed to insert or update core')) {
		return false;
	    }
	} elseif (!$row) {
	    $_SESSION['error_code'] = 200;
	    $_SESSION['error_message'] = 'Failed to retrieve core details.';
	    return false;
	}
    }


// ---- Success

    $_SESSION['error_code'] = 0;
    $_SESSION['error_message'] = 'OK';

    $_SESSION['character_id'] = $character_id;
    $_SESSION['character_name'] = $character_name;

    $_SESSION['mumble_username'] = $mumble_username;
    $_SESSION['mumble_password'] = $mumble_password;

    $_SESSION['corporation_id'] = $corporation_id;
    $_SESSION['corporation_name'] = $corporation_name;
    $_SESSION['corporation_ticker'] = $corporation_ticker;

    $_SESSION['faction_id'] = $faction_id;
    $_SESSION['faction_name'] = $faction_name;

    $_SESSION['alliance_id'] = $alliance_id;
    $_SESSION['alliance_name'] = $alliance_name;
    $_SESSION['alliance_ticker'] = $alliance_ticker;

    $_SESSION['tags'] = $tags;
    $_SESSION['groups'] = $groups;
    $_SESSION['perms'] = $perms;

    $_SESSION['updated_at'] = $ts_now;

    return true;
}

function refresh_users() {
    global $cfg_sql_url, $cfg_sql_user, $cfg_sql_pass, $cfg_user_agent, $cfg_refresher_users;

    try {
	$dbr = new PDO($cfg_sql_url, $cfg_sql_user, $cfg_sql_pass);
    } catch (PDOException $e) {
	    echo "FAIL: Failed to connect to the database.\n";
	    return false;
    }

    $stm = $dbr->prepare('SELECT * FROM user WHERE updated_at < :updated_at');
    $stm->bindValue(':updated_at', time() - $cfg_refresher_users);
    if (!db_exec($stm, 300, 'Failed to find characters.')) {
	return false;
    }

    $countUsers = 0;
    $countUsersFailed = 0;

    while ($row = $stm->fetch()) {
	sleep(1);
	echo "Updating: " . $row['character_id'] . ".\n";
	$countUsers++;
	if (!update_user($dbr, $row['character_id'], $row['owner_hash'])) {
	    $countUsersFailed++;
	    echo "FAILED.\n";
	}
    }

    echo "Users updated: " . $countUsers . "\n";
    echo "Users failed: " . $countUsersFailed . "\n";

    return true;
}

function refresh_alliances() {
    global $cfg_sql_url, $cfg_sql_user, $cfg_sql_pass, $cfg_user_agent;

    try {
	$dbr = new PDO($cfg_sql_url, $cfg_sql_user, $cfg_sql_pass);
    } catch (PDOException $e) {
	echo "FAIL: Failed to connect to the database.\n";
	return false;
    }

    $stm = $dbr->prepare('SELECT alliance_id FROM alliance');
    if (!$stm->execute()) {
	echo "FAIL: Could not load alliances.\n";
	return false;
    }

    $ids = array();
    while ($row = $stm->fetch()) {
	array_push($ids, $row['alliance_id']);
    }

    $options = array(
	'http' => array(
	    'method'  => 'GET',
	    'header'  => array(
		'Host: api.eveonline.com',
		'User-Agent: ' . $cfg_user_agent,
	    ),
        ),
    );

    $xml = file_get_contents('https://api.eveonline.com/eve/AllianceList.xml.aspx', false, stream_context_create($options));
    if (!$xml) {
	echo "FAIL: API has no results.\n";
	return false;
    }

    $apiInfo = new SimpleXMLElement($xml);
    $row = $apiInfo->result->rowset->row;

    $countAlliance = 0;
    $countAllianceAdded = 0;
    $countAllianceUpdated = 0;

    $dbr->beginTransaction();
    $stmI = $dbr->prepare('INSERT INTO alliance (alliance_id, alliance_name, alliance_ticker) VALUES (:alliance_id, :alliance_name, :alliance_ticker)');
    $stmU = $dbr->prepare('UPDATE alliance set alliance_name = :alliance_name, alliance_ticker = :alliance_ticker WHERE alliance_id = :alliance_id');

    foreach($row as $r) {
	$countAlliance++;

	$aid =  $r->attributes()->allianceID;
	$aname = $r->attributes()->name;
	$ashort = $r->attributes()->shortName;

	if (in_array($aid, $ids)) {
	    $stmU->bindValue(':alliance_id', $aid);
	    $stmU->bindValue(':alliance_name', $aname);
	    $stmU->bindValue(':alliance_ticker', $ashort);
	    $stmU->execute();
	    $countAllianceUpdated++;
	} else {
	    $stmI->bindValue(':alliance_id', $aid);
	    $stmI->bindValue(':alliance_name', $aname);
	    $stmI->bindValue(':alliance_ticker', $ashort);
	    $stmI->execute();
	    $countAllianceAdded++;
	}
    }

    $dbr->commit();

    echo "Alliances found: " . $countAlliance . "\n";
    echo "Alliances added: " . $countAllianceAdded . "\n";
    echo "Alliances updated: " . $countAllianceUpdated . "\n";

    return true;
}

function ismod() {
    $perms = explode(', ', $_SESSION['perms']);
    return in_array('mumble.moderator', $perms);
}

?>

