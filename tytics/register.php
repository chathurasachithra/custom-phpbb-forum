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

$user_name = $request->variable('user_name', '');
$password = $request->variable('password', '');
$email = $request->variable('email', '');

if ($user_name == '' || $password == '' || $email == '') {
    echo json_encode (['success' => false, 'reason' => 'Validation error']);
    exit();
}

$user_row = array(
    'username'              => $user_name,
    'user_password'         => phpbb_hash($password),
    'user_email'            => $email,
    'group_id'              => 2, // by default, the REGISTERED user group is id 2
    'user_timezone'         => (float) $data['tz'],
    'user_lang'             => $data['lang'],
    'user_type'             => USER_NORMAL,
    'user_ip'               => $user->ip,
    'user_regdate'          => time(),
);

try {
    $user_id = user_add($user_row); // This is where the code fails and returns the error
    echo json_encode (['success' => true, 'user_id' => $user_id]);
} catch (Exception $ex) {
    echo json_encode (['success' => false, 'reason' => $ex->getMessage()]);
}
exit();