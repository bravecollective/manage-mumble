<?php
    define('GUEST', 23);
    include_once('config.php');
    include_once('helper.php');

    sstart();
?>

<?php
    update_pass();
    header('Location: ' . $cfg_url_base);
?>
