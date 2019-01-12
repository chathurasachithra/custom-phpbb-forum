<?php
/**
* This file is part of tytics custom authentication.
*/

/*define('IN_PHPBB', true);
$phpbb_root_path = '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
$user->setup();
$user_id = $request->variable('user_id', '');
$result = $user->session_create($user_id, false, true, true);
var_dump($result);*/

define('IN_PHPBB', true);
$phpbb_root_path = '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.php');

try {
    $user->session_begin();
    $auth->acl($user->data);
    $user->setup();

    $requestData = json_decode(file_get_contents("php://input"));
    $user_name = (isset($requestData->user_name)) ? $requestData->user_name : '';
    $password = (isset($requestData->password)) ? $requestData->password : '';
    $remember = true;
    $result = $auth->login($user_name, $password, $remember);
    if (isset($result['status']) && $result['status'] == LOGIN_SUCCESS) {
        echo json_encode (['success' => true, 'session' => $user->session_id]);
    } else {
        echo json_encode (['success' => false, 'reason' => $result['error_msg']]);
    }
} catch (Exception $ex) {
    echo json_encode (['success' => false, 'reason' => $ex->getMessage()]);
}


