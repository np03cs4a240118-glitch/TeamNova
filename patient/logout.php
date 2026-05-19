<?php
session_start();
session_destroy();
header('Location: /medibook/patient/login.php');
exit;
