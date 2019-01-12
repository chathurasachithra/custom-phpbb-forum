<?php
/**
* This file is part of tytics custom authentication.
*/

define('IN_PHPBB', true);
$phpbb_root_path = '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.php');
include($phpbb_root_path . 'config.php');
include($phpbb_root_path . 'includes/functions_user.php');
include($phpbb_root_path . 'includes/ucp/ucp_register.php');

$user_id = $request->variable('user_id', '');
$password = $request->variable('password', '');

if ($user_id == '') {
    echo json_encode (['success' => false, 'reason' => 'Validation error']);
    exit();
}

try {
    $sql = "UPDATE tytics_users" .
        " SET user_password = '" . phpbb_hash($password) . "'" .
        " WHERE user_id = " . $user_id;
    $response = $db->sql_query($sql);
    if ($response) {
        echo json_encode(['success' => true, 'user_id' => $user_id]);
    } else {
        echo json_encode(['success' => false, 'user_id' => $user_id]);
    }
} catch (Exception $ex) {
    echo json_encode (['success' => false, 'reason' => $ex->getMessage()]);
}
exit();