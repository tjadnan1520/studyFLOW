<?php
require_once 'config/database.php';

session_destroy();
redirect('index.php');
?>