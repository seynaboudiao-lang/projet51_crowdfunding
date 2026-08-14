/**
 * validation.js
 * Validation JavaScript côté client, complémentaire aux contrôles HTML5
 * (required, minlength, type="email") ET aux contrôles PHP côté serveur
 * (qui restent la protection réelle — le JS n'est qu'un confort utilisateur).
 */
document.addEventListener('DOMContentLoaded', function () {

    // ---------------------------------------------------------------
    // 1) Formulaire d'inscription : confirmation du mot de passe
    // ---------------------------------------------------------------
    const formInscription = document.querySelector('form[action*="register"], #form-inscription');
    const mdp = document.querySelector('input[name="mot_de_passe"]');
    const mdpConfirm = document.querySelector('input[name="mot_de_passe_confirm"]');

    if (mdp && mdpConfirm) {
        const verifierCorrespondance = function () {
            if (mdpConfirm.value && mdp.value !== mdpConfirm.value) {
                mdpConfirm.setCustomValidity('Les deux mots de passe ne correspondent pas.');
            } else {
                mdpConfirm.setCustomValidity('');
            }
        };
        mdp.addEventListener('input', verifierCorrespondance);
        mdpConfirm.addEventListener('input', verifierCorrespondance);

        const formParent = mdpConfirm.closest('form');
        if (formParent) {
            formParent.addEventListener('submit', function (e) {
                verifierCorrespondance();
                if (!formParent.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                formParent.classList.add('was-validated');
            });
        }
    }

    // ---------------------------------------------------------------
    // 2) Validation générique Bootstrap pour tous les formulaires
    //    marqués data-validation="stricte"
    // ---------------------------------------------------------------
    document.querySelectorAll('form[data-validation="stricte"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // ---------------------------------------------------------------
    // 3) Montant de contribution : doit être un nombre positif
    // ---------------------------------------------------------------
    const champMontant = document.querySelector('input[name="montant"]');
    if (champMontant) {
        champMontant.addEventListener('input', function () {
            const valeur = parseFloat(champMontant.value);
            if (isNaN(valeur) || valeur <= 0) {
                champMontant.setCustomValidity('Le montant doit être un nombre positif.');
            } else {
                champMontant.setCustomValidity('');
            }
        });
    }

    // ---------------------------------------------------------------
    // 4) Téléphone sénégalais : format 7X XXX XX XX (9 chiffres, débute par 7)
    // ---------------------------------------------------------------
    const champTelephone = document.querySelector('input[name="telephone"]');
    if (champTelephone) {
        champTelephone.addEventListener('input', function () {
            const chiffres = champTelephone.value.replace(/\D/g, '');
            const regexSenegal = /^7[0-8][0-9]{7}$/;
            if (chiffres && !regexSenegal.test(chiffres)) {
                champTelephone.setCustomValidity('Numéro sénégalais invalide (format attendu : 7X XXX XX XX).');
            } else {
                champTelephone.setCustomValidity('');
            }
        });
    }

    // ---------------------------------------------------------------
    // 5) Dates de campagne : date de fin doit être après la date de début
    // ---------------------------------------------------------------
    const dateDebut = document.querySelector('input[name="date_debut"]');
    const dateFin = document.querySelector('input[name="date_fin"]');
    if (dateDebut && dateFin) {
        const verifierDates = function () {
            if (dateDebut.value && dateFin.value && dateFin.value <= dateDebut.value) {
                dateFin.setCustomValidity('La date de fin doit être postérieure à la date de début.');
            } else {
                dateFin.setCustomValidity('');
            }
        };
        dateDebut.addEventListener('change', verifierDates);
        dateFin.addEventListener('change', verifierDates);
    }

});
