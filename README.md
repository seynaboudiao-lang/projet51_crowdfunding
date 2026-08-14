# Projet 51 — Plateforme de crowdfunding pour projets entrepreneuriaux
### Master CCA — École Supérieure Polytechnique de Dakar — 2025-2026

Plateforme web permettant à des porteurs de projets entrepreneuriaux sénégalais
de lancer des campagnes de financement participatif, et à des contributeurs de
les soutenir via les opérateurs mobile money locaux (Wave, Orange Money, Free Money).

## Stack technique

- **Backend** : PHP 8+ orienté objet, PDO (requêtes préparées)
- **Base de données** : MySQL / MariaDB (via XAMPP + phpMyAdmin)
- **Frontend** : HTML5, CSS3, Bootstrap 5, JavaScript, Chart.js
- **Sécurité** : `password_hash()`, sessions sécurisées, jetons CSRF, `htmlspecialchars()`, requêtes préparées

## Prérequis

- [XAMPP](https://www.apachefriends.org/) avec Apache, MySQL et PHP 8.0 ou supérieur
- Un navigateur web récent

## Installation

1. **Copier le projet** dans le dossier `htdocs` de XAMPP :
   ```
   C:\xampp\htdocs\projet51_crowdfunding\   (Windows)
   /Applications/XAMPP/htdocs/projet51_crowdfunding/   (Mac)
   ```

2. **Démarrer Apache et MySQL** depuis le panneau de contrôle XAMPP.

3. **Créer la base de données** :
   - Ouvrir phpMyAdmin (`http://localhost/phpmyadmin`)
   - Onglet **Importer** → sélectionner `sql/schema.sql` → **Exécuter**
   - Cela crée la base `crowdfunding_esp` avec ses 9 tables et les catégories de démonstration.

4. **Générer les comptes de démonstration et les données réalistes** :
   - Dans le navigateur, ouvrir :
     `http://localhost/projet51_crowdfunding/sql/seed_demo.php`
   - Ce script crée les comptes utilisateurs avec des mots de passe **correctement
     hachés avec `password_hash()`** (impossible de les insérer en clair dans le
     `.sql` de façon fiable) ainsi que des campagnes, paliers, contributions et
     paiements réalistes.
   - ⚠️ À exécuter **une seule fois**. Si vous devez réinitialiser, réimportez
     d'abord `schema.sql` (il supprime et recrée la base).

5. **Vérifier la configuration** dans `config/config.php` si votre installation
   XAMPP diffère de la configuration par défaut (utilisateur `root` sans mot
   de passe, `localhost`).

6. **Accéder à l'application** :
   `http://localhost/projet51_crowdfunding/public/index.php`

## Comptes de test

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | admin@crowdfunding.sn | Admin@2026 |
| Porteur de projet | porteur@crowdfunding.sn | Porteur@2026 |
| Contributeur | contributeur@crowdfunding.sn | Contrib@2026 |

## Arborescence du projet

```
projet51_crowdfunding/
├── config/
│   ├── config.php          # Constantes, session sécurisée
│   └── database.php        # Connexion PDO (singleton)
├── includes/
│   ├── auth.php             # Classe Auth (inscription, connexion, rôles)
│   ├── functions.php        # Fonctions utilitaires (CSRF, audit, pagination, mobile money...)
│   ├── mailer.php            # Envoi d'email de confirmation (mail() natif)
│   ├── lib/SimplePDF.php     # Générateur PDF minimaliste sans dépendance
│   ├── header.php           # En-tête HTML + navigation
│   └── footer.php           # Pied de page HTML
├── modules/
│   ├── auth/                 # login.php, register.php, logout.php
│   ├── dashboard/             # index.php (KPI + graphique Chart.js)
│   ├── campagnes/             # CRUD complet (entité principale)
│   │   ├── liste.php           # Liste + recherche + filtres + pagination
│   │   ├── creer.php           # Create
│   │   ├── detail.php          # Read (+ bouton Contribuer, gestion paliers)
│   │   ├── modifier.php        # Update
│   │   ├── supprimer.php       # Delete
│   │   └── export_csv.php      # Export Excel/CSV
│   ├── paliers/                # CRUD des contreparties d'une campagne
│   │   ├── liste.php / creer.php / modifier.php / supprimer.php
│   ├── contributions/           # Flux de contribution + historique
│   │   ├── contribuer.php        # Formulaire (palier, montant, opérateur)
│   │   ├── liste.php             # Vue porteur/admin (filtres, pagination, export)
│   │   ├── mes_contributions.php # Historique du contributeur + annulation
│   │   ├── annuler.php           # Annulation d'une contribution en attente
│   │   ├── recu_pdf.php          # Export PDF du reçu officiel (SimplePDF)
│   │   └── export_csv.php
│   ├── paiements/                # Simulation du paiement mobile money
│   │   ├── traiter.php            # Confirmation / échec (callback simulé)
│   │   ├── liste.php              # Historique des transactions (admin)
│   │   └── export_csv.php
│   └── admin/
│       └── utilisateurs.php    # Gestion des utilisateurs (admin)
├── public/
│   ├── index.php             # Point d'entrée (redirige selon la session)
│   └── assets/css/style.css
├── sql/
│   ├── schema.sql             # Structure de la BDD (9 tables + FK)
│   └── seed_demo.php          # Génération des comptes + données de démo
└── README.md
```

## Ce qui est déjà fonctionnel dans ce socle

- Authentification sécurisée (hash bcrypt, régénération d'ID de session, CSRF)
- 3 rôles distincts avec contrôle d'accès (`Auth::exigerRole()`)
- CRUD complet sur les entités **campagnes**, **paliers**, **contributions** et **paiements**
- Flux de contribution complet : choix d'un palier ou montant libre → sélection de
  l'opérateur (Wave / Orange Money / Free Money / carte) → simulation du callback
  de confirmation → mise à jour automatique du montant collecté, du palier et du
  statut de la campagne (passage à "réussie" si l'objectif est atteint)
- Recherche multi-critères, filtres et pagination sur toutes les listes
- Tableau de bord avec KPI et graphique dynamique (Chart.js)
- Export CSV/Excel (campagnes, contributions, transactions)
- Export PDF du reçu officiel de contribution (`SimplePDF`, sans dépendance)
- Envoi d'email automatique de confirmation après validation d'un paiement
- Journal d'audit horodaté (`journal_audit`) sur les actions sensibles
- Interface responsive (Bootstrap 5)

## Simulation du paiement mobile money — pourquoi et comment

Wave, Orange Money et Free Money ne mettent pas à disposition des étudiants
un environnement de test (sandbox) public. La page `modules/paiements/traiter.php`
reproduit donc, de façon assumée et documentée dans le code, le comportement
attendu du **webhook de confirmation** que l'opérateur appellerait normalement
côté serveur une fois le client ayant validé l'opération sur son téléphone
(saisie du code PIN Wave, requête USSD Orange Money...).

Pour la soutenance, expliquez clairement cette limite technique et ce que
vous avez fait pour la contourner pédagogiquement — c'est exactement le type
de recul attendu par le cahier des charges (section "Ce que vous devez
maîtriser vous-même"). Si vous disposez de vraies clés API sandbox (ex. Wave
Checkout), vous pouvez remplacer le bouton "Confirmer" par un appel réel à
l'API et ne garder la logique métier existante (mise à jour BDD, email) que
dans l'endpoint qui recevrait le webhook réel.

## Configurer l'envoi d'email (fonction `mail()`)

`includes/mailer.php` utilise la fonction native `mail()` de PHP (pas de
PHPMailer, pour rester sans installation via composer). Par défaut, XAMPP
n'a **pas** de serveur mail configuré : `mail()` renverra alors `false` et
l'échec sera simplement journalisé (l'application ne plante jamais pour
autant). Pour voir réellement partir l'email pendant vos tests :

- **Windows** : configurez `sendmail.ini` (dossier `sendmail/` de XAMPP) avec
  les identifiants d'un compte SMTP (Gmail avec mot de passe d'application,
  Mailtrap, etc.), puis dans `php.ini` : `sendmail_path = "C:\xampp\sendmail\sendmail.exe -t"`.
- **Mac/Linux** : renseignez `SMTP` et `smtp_port` dans `php.ini`, ou utilisez
  un outil de capture local comme Mailtrap/Mailpit pour visualiser les emails
  sans les envoyer réellement.
- Redémarrez Apache après toute modification de `php.ini`.

## Export PDF sans dépendance (`SimplePDF`)

`includes/lib/SimplePDF.php` construit directement un PDF valide (catalogue,
police standard, flux de texte, table `xref`) sans bibliothèque externe, pour
rester conforme à l'exigence "exécutable sur tout poste XAMPP standard sans
installation complexe". Il est volontairement simple (texte, titres, paires
libellé/valeur, séparateurs) — suffisant pour un reçu ou un état officiel.
Pour un besoin plus riche (tableaux complexes, images, pagination automatique
multi-pages), vous pouvez le remplacer par TCPDF ou DOMPDF via composer.

## À compléter par vous (travail personnel exigé par le sujet)

Le cahier des charges impose que chaque étudiant comprenne et complète son
projet — ce socle est un point de départ technique déjà fonctionnel de bout
en bout. Reste, selon votre temps disponible, à approfondir :

- [ ] Intégration d'une vraie API mobile money si vous obtenez des clés sandbox
- [ ] Espace public de découverte des campagnes (sans connexion)
- [ ] Module de notifications in-app (table `notifications` déjà prête)
- [ ] Manuel utilisateur PDF (15-20 pages, captures d'écran) et document technique
- [ ] Tests manuels de sécurité (tentatives d'injection SQL, XSS, contournement de rôle)
- [ ] Vidéo de présentation de 8 à 15 minutes

## Sécurité — points déjà couverts

- Mots de passe hachés avec `password_hash()` / vérifiés avec `password_verify()`
- 100% des requêtes SQL utilisent des requêtes préparées PDO (`PDO::ATTR_EMULATE_PREPARES => false`)
- Toutes les sorties utilisateur passent par `htmlspecialchars()` via la fonction `e()`
- Jetons CSRF sur tous les formulaires POST
- Sessions `httponly`, `samesite=Lax`, régénération d'ID à la connexion
- Expiration automatique de session après 30 minutes d'inactivité
- Contrôle des rôles côté serveur sur chaque page sensible (jamais côté client seul)
