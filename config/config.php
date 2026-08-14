<?php
/**
 * config/config.php
 * Configuration générale de l'application + démarrage sécurisé de la session.
 * Projet 51 — Plateforme de crowdfunding — Master CCA ESP Dakar
 */

// ---------------------------------------------------------------------------
// Paramètres de connexion à la base de données (à adapter si besoin)
// ---------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'crowdfunding_esp');
define('DB_USER', 'root');
define('DB_PASS', '');       // mot de passe root XAMPP par défaut = vide
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------------
// Paramètres généraux de l'application
// ---------------------------------------------------------------------------
define('APP_NAME', 'FinancePartagée Sénégal');
define('APP_URL', 'http://localhost/projet51_crowdfunding/public');
define('COMMISSION_PLATEFORME', 0.05); // 5% de commission sur les campagnes réussies
define('ITEMS_PAR_PAGE', 10);

// Durée d'inactivité avant expiration automatique de session (en secondes)
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes

// ---------------------------------------------------------------------------
// Affichage des erreurs (à mettre à false en production)
// ---------------------------------------------------------------------------
define('APP_DEBUG', true);
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ---------------------------------------------------------------------------
// Démarrage sécurisé de la session (avant tout envoi de contenu)
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // empêche l'accès au cookie via JavaScript (protection XSS)
        'samesite' => 'Lax',  // protection CSRF basique
        // 'secure' => true,   // à activer en production (HTTPS)
    ]);
    session_start();
}

// Déconnexion automatique après expiration d'inactivité
if (isset($_SESSION['derniere_activite']) && (time() - $_SESSION['derniere_activite'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/../modules/auth/login.php?expired=1');
    exit;
}
$_SESSION['derniere_activite'] = time();

// Fuseau horaire
date_default_timezone_set('Africa/Dakar');
