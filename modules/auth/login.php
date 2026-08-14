<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (Auth::estConnecte()) {
    redirect(APP_URL . '/../modules/dashboard/index.php');
}

$erreur = '';
$expired = isset($_GET['expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $auth = new Auth();
        [$succes, $message] = $auth->connecter($_POST['email'] ?? '', $_POST['mot_de_passe'] ?? '');
        if ($succes) {
            redirect(APP_URL . '/../modules/dashboard/index.php');
        }
        $erreur = $message;
    }
}

$pageTitre = 'Connexion';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-hero">
        <div class="auth-hero__marque"><i class="bi bi-cash-coin"></i> <?= e(APP_NAME) ?></div>
        <div>
            <h1 class="auth-hero__titre">Tissons vos projets, un don à la fois.</h1>
            <p class="auth-hero__texte">
                Publiez une campagne, fixez vos paliers, et laissez la diaspora et
                le grand public la financer par Wave, Orange Money ou Free Money —
                chaque contribution tisse un peu plus l'étoffe du projet.
            </p>
        </div>
        <div class="auth-hero__stats">
            <div><strong>3</strong><span>Opérateurs mobile money</span></div>
            <div><strong>70+</strong><span>Projets Master CCA</span></div>
            <div><strong>0 F</strong><span>Frais de découverte</span></div>
        </div>
    </div>

    <div class="auth-form">
        <div class="tarjeta">
            <h4 class="fw-bold mb-1">Content de vous revoir</h4>
            <p class="text-muted small mb-4">Connectez-vous pour suivre vos campagnes ou vos contributions.</p>

            <?php if ($expired): ?>
                <div class="alert alert-warning">Votre session a expiré, veuillez vous reconnecter.</div>
            <?php endif; ?>
            <?php if ($erreur): ?>
                <div class="alert alert-danger"><?= e($erreur) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">Adresse email</label>
                    <input type="email" name="email" class="form-control" required autofocus
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="mot_de_passe" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Se connecter
                </button>
            </form>

            <p class="text-center mt-3 mb-0">
                <small>Pas encore de compte ?
                    <a href="register.php">Créer un compte</a>
                </small>
            </p>

            <hr>
            <p class="small text-muted mb-2"><strong>Comptes de démonstration</strong></p>
            <ul class="small text-muted mb-0 list-unstyled d-flex flex-column gap-1">
                <li>Admin — <code>admin@crowdfunding.sn</code> / <code>Admin@2026</code></li>
                <li>Porteur — <code>porteur@crowdfunding.sn</code> / <code>Porteur@2026</code></li>
                <li>Contributeur — <code>contributeur@crowdfunding.sn</code> / <code>Contrib@2026</code></li>
            </ul>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
