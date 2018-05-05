<?php
    define('GUEST', 23);

    if (PHP_SAPI != 'cli') {
	die("nope!");
    }

    include_once('../webroot/config.php');
    include_once('../webroot/helper.php');

    $tusers = 0;
    $tallies = 0;

    while(true) {

	if (time() - $tusers >= $cfg_refresher_users) {
	    echo "Refreshing users....\n";
	    refresh_users();
	    echo "done. sleeping.\n";
	    $tusers = time();
	}

	if (time() - $tallies >= $cfg_refresher_alliances) {
	    echo "Refreshing alliances....\n";
	    refresh_alliances();
	    echo "done. sleeping.\n";
	    $tallies = time();
	}

	sleep(60);

    }
?>
