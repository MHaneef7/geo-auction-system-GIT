<?php
// Admin logout - destroy session and redirect to login page
session_start();
session_unset();
session_destroy();
header("Location: ../index.php");
exit();

