<?php
    define('GUEST', 23);
    include_once('config.php');
    include_once('helper.php');

    sstart();
?>

<?php

if (!ismod()) {
    die("nope");
}

$arg1 = "";
$arg2 = "";
$arg3 = "";
$arg4 = "";

if (isset($_GET['arg1'])) { $arg1 = escapeshellarg($_GET['arg1']); }
if (isset($_GET['arg2'])) { $arg2 = escapeshellarg($_GET['arg2']); }
if (isset($_GET['arg3'])) { $arg3 = escapeshellarg($_GET['arg3']); }
if (isset($_GET['arg4'])) { $arg4 = escapeshellarg($_GET['arg4']); }

passthru("cd ../admin ; /usr/bin/python mumble-sso-core-admin.py " . $arg1 . ' ' . $arg2 . ' ' . $arg3 . ' ' . $arg4);

?>
