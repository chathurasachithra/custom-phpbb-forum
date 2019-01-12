<?php
/**
* This file is part of tytics custom authentication.
*/

define('IN_PHPBB', true);
$phpbb_root_path = '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

// Start session
$user->session_begin();
try {
    if ($user->data['user_id'] != ANONYMOUS &&  isset($user->session_id))
    {
       $user->session_kill();
    }
    echo json_encode (['success' => true]);
} catch (Exception $ex) {
    echo json_encode (['success' => false, 'reason' => $ex->getMessage()]);
}


