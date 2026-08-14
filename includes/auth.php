<?php
/**
 * includes/auth.php
 * Classe Auth : inscription, connexion sécurisée, contrôle d'accès par rôle.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class Auth
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Inscrit un nouvel utilisateur (rôle "porteur" ou "contributeur").
     * Le mot de passe est haché avec password_hash() — jamais stocké en clair.
     * Retourne [true, ''] en cas de succès ou [false, 'message erreur'].
     */
    public function inscrire(string $nom, string $prenom, string $email, string $telephone, string $motDePasse, string $role): array
    {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Adresse email invalide.'];
        }
        if (!in_array($role, ['porteur', 'contributeur'], true)) {
            return [false, 'Rôle invalide.'];
        }
        if (strlen($motDePasse) < 8) {
            return [false, 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM utilisateurs WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return [false, 'Cet email est déjà utilisé.'];
        }

        $hash = password_hash($motDePasse, PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, role)
             VALUES (:nom, :prenom, :email, :tel, :hash, :role)'
        );
        $stmt->execute([
            ':nom'    => trim($nom),
            ':prenom' => trim($prenom),
            ':email'  => $email,
            ':tel'    => trim($telephone),
            ':hash'   => $hash,
            ':role'   => $role,
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        journaliser($this->pdo, $userId, 'INSCRIPTION', 'utilisateurs', $userId);

        return [true, ''];
    }

    /**
     * Authentifie un utilisateur. Retourne [true, ''] ou [false, 'message erreur'].
     * Utilise password_verify() et régénère l'ID de session (protection fixation de session).
     */
    public function connecter(string $email, string $motDePasse): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM utilisateurs WHERE email = :email');
        $stmt->execute([':email' => trim($email)]);
        $utilisateur = $stmt->fetch();

        // Message volontairement générique (ne pas révéler si l'email existe ou non)
        $erreurGenerique = 'Email ou mot de passe incorrect.';

        if (!$utilisateur || !password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
            return [false, $erreurGenerique];
        }
        if ($utilisateur['statut'] !== 'actif') {
            return [false, 'Ce compte est suspendu. Contactez l\'administrateur.'];
        }

        session_regenerate_id(true);

        $_SESSION['user_id']    = (int) $utilisateur['id'];
        $_SESSION['user_nom']   = $utilisateur['nom'];
        $_SESSION['user_prenom']= $utilisateur['prenom'];
        $_SESSION['user_email'] = $utilisateur['email'];
        $_SESSION['user_role']  = $utilisateur['role'];
        $_SESSION['derniere_activite'] = time();

        $maj = $this->pdo->prepare('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = :id');
        $maj->execute([':id' => $utilisateur['id']]);

        journaliser($this->pdo, (int) $utilisateur['id'], 'CONNEXION', 'utilisateurs', (int) $utilisateur['id']);

        return [true, ''];
    }

    public function deconnecter(): void
    {
        if (isset($_SESSION['user_id'])) {
            journaliser($this->pdo, (int) $_SESSION['user_id'], 'DECONNEXION', 'utilisateurs', (int) $_SESSION['user_id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function estConnecte(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function utilisateurId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Bloque l'accès à la page si l'utilisateur n'est pas connecté.
     */
    public static function exigerConnexion(): void
    {
        if (!self::estConnecte()) {
            redirect(APP_URL . '/../modules/auth/login.php');
        }
    }

    /**
     * Bloque l'accès à la page si le rôle de l'utilisateur n'est pas autorisé.
     * Exemple : Auth::exigerRole(['admin', 'porteur']);
     */
    public static function exigerRole(array $rolesAutorises): void
    {
        self::exigerConnexion();
        if (!in_array(self::role(), $rolesAutorises, true)) {
            http_response_code(403);
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                    <h1>403 — Accès refusé</h1>
                    <p>Vous n\'avez pas les droits nécessaires pour accéder à cette page.</p>
                    <a href="' . APP_URL . '/../modules/dashboard/index.php">Retour au tableau de bord</a>
                 </div>');
        }
    }
}
