<?php
include_once './old/class.UserSessionHandler.php';

$action = (isset($_POST['action'])) ? $_POST['action'] : $_GET['action'];

if (!UserSessionHandler::prepareSession() && $action != 'login') {
	echo 'ERR_YOU_ARE_NOT_LOGGED';
	exit;
}


switch($action) {
	case 'fieldnotes':
    require_once 'function/gc_post_field_notes.php';
    break;
	default:
    header("HTTP/1.1 406 Not Acceptable", true, 406);
    break;
}
