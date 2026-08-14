<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (Auth::estConnecte()) {
    redirect(APP_URL . '/../modules/dashboard/index.php');
}

$erreur = '';
$succes = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } elseif (($_POST['mot_de_passe'] ?? '') !== ($_POST['mot_de_passe_confirm'] ?? '')) {
        $erreur = 'Les deux mots de passe ne correspondent pas.';
    } else {
        $auth = new Auth();
        [$succes, $message] = $auth->inscrire(
            $_POST['nom'] ?? '',
            $_POST['prenom'] ?? '',
            $_POST['email'] ?? '',
            $_POST['telephone'] ?? '',
            $_POST['mot_de_passe'] ?? '',
            $_POST['role'] ?? 'contributeur'
        );
        $erreur = $succes ? '' : $message;
    }
}

$pageTitre = 'Créer un compte';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-hero">
        <div class="auth-hero__marque"><i class="bi bi-cash-coin"></i> <?= e(APP_NAME) ?></div>
        <div>
            <h1 class="auth-hero__titre">Deux façons de rejoindre l'étoffe commune.</h1>
            <p class="auth-hero__texte">
                Portez un projet et fixez vos paliers de contrepartie, ou soutenez
                ceux des autres en quelques francs via mobile money. Les deux
                comptent, et les deux se suivent depuis un même tableau de bord.
            </p>
        </div>
        <div class="auth-hero__stats">
            <div><strong>2</strong><span>Rôles à l'inscription</span></div>
            <div><strong>&lt; 2 min</strong><span>Pour créer un compte</span></div>
            <div><strong>0 F</strong><span>Frais d'inscription</span></div>
        </div>
    </div>

    <div class="auth-form">
        <div class="tarjeta tarjeta-large">
            <h4 class="fw-bold mb-1">Créer un compte</h4>
            <p class="text-muted small mb-4">Choisissez votre rôle : porteur de projet ou contributeur.</p>

            <?php if ($succes): ?>
                <div class="alert alert-success">
                    Compte créé avec succès ! Vous pouvez maintenant
                    <a href="login.php">vous connecter</a>.
                </div>
            <?php else: ?>
                <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="prenom" class="form-control" required
                                   value="<?= e($_POST['prenom'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" name="nom" class="form-control" required
                                   value="<?= e($_POST['nom'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adresse email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" name="telephone" class="form-control" required
                               placeholder="77 000 00 00" value="<?= e($_POST['telephone'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Je souhaite m'inscrire en tant que</label>
                        <select name="role" class="form-select" required>
                            <option value="contributeur">Contributeur (je veux financer des projets)</option>
                            <option value="porteur">Porteur de projet (je veux lancer une campagne)</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" name="mot_de_passe" class="form-control" required minlength="8">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirmation</label>
                            <input type="password" name="mot_de_passe_confirm" class="form-control" required minlength="8">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Créer mon compte</button>
                </form>
            <?php endif; ?>

            <p class="text-center mt-3 mb-0">
                <small>Déjà inscrit ? <a href="login.php">Se connecter</a></small>
            </p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
