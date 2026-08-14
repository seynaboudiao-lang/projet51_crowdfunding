<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (Auth::estConnecte()) {
    redirect(APP_URL . '/../modules/dashboard/index.php');
} else {
    redirect(APP_URL . '/../modules/auth/login.php');
}
