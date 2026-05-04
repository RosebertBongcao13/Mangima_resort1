<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: /mangima_resort/auth/login.php');
exit;