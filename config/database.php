<?php
/**
 * config/database.php
 * Connexion PDO à MySQL en mode singleton.
 * Toutes les requêtes de l'application DOIVENT passer par cette instance
 * et utiliser des requêtes préparées (protection contre les injections SQL).
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    // Empêche l'instanciation directe (pattern singleton)
    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // vraies requêtes préparées côté serveur
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // On ne renvoie jamais le message brut de PDO à l'écran (fuite d'info)
                error_log('Erreur connexion BDD : ' . $e->getMessage());
                die('Erreur de connexion à la base de données. Contactez l\'administrateur.');
            }
        }
        return self::$instance;
    }
}
