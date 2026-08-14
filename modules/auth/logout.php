<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth = new Auth();
$auth->deconnecter();

redirect(APP_URL . '/../modules/auth/login.php');
