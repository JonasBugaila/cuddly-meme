<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';
start_session();
if (!is_logged_in()) {
    redirect('../login.php');
}
display_konkursai_calendar();

?>