# Mise en ligne de Lost&Found AI

Ce document liste **tout ce qui reste à faire pour mettre le site en ligne**, dans l'ordre.
Le code est prêt. Les étapes ci-dessous demandent des comptes, des paiements, des
informations personnelles ou un accès à l'hébergeur : elles ne peuvent pas être faites
depuis le code.

Cochez les cases au fur et à mesure.

---

## 0. Ce qui est déjà fait dans le code

| Fonctionnalité | Où |
|---|---|
| Mot de passe oublié (lien par email, 1 h, usage unique, déconnecte toutes les sessions) | `PasswordController` |
| Page « Mon compte » : changer de mot de passe, alertes, **export RGPD**, **suppression du compte** | `AccountController`, `/account` |
| Mentions légales, politique de confidentialité, CGU (case obligatoire à l'inscription) | `PageController`, `/mentions-legales`, `/confidentialite`, `/cgu` |
| Signalement d'annonces + page de modération | `ReportController`, `AdminController`, `/admin/reports` |
| Livraison payante **masquée automatiquement en production** tant que Stripe et un vrai transporteur ne sont pas branchés. Remise en main propre via la messagerie à la place | `ShippingService::isEnabled()` |
| Nettoyage quotidien (codes expirés, journaux de 24 h, comptes non vérifiés de plus de 7 jours) | `bin/cleanup.php` |
| Création d'un administrateur en ligne de commande | `bin/make-admin.php` |
| `.htaccess` à la racine : protège `config/`, `storage/` et `src/` si la racine web ne peut pas pointer sur `public/` | `.htaccess` |
| `debug` à `false` par défaut dans le modèle de configuration | `config/config.example.php` |
| Le serveur SMTP reçoit votre vrai domaine (`EHLO`), ce qui évite des points anti-spam | `Mailer::heloDomain()` |
| `robots.txt` (pages privées non indexées) | `public/robots.txt` |

---

## 1. Hébergement et nom de domaine

- [ ] **Acheter un nom de domaine** (environ 10 €/an, par exemple chez Hostinger, OVH ou Gandi).
- [ ] **Souscrire un hébergement PHP**. Un mutualisé Hostinger *Premium* ou *Business* suffit : il donne l'accès SSH, les tâches cron, MariaDB et le SSL gratuit.
- [ ] Dans le panneau de l'hébergeur, choisir **PHP 8.2 ou plus récent**.
- [ ] Vérifier que ces **extensions PHP** sont activées (hPanel > Avancé > Configuration PHP > Extensions) :
  `pdo_mysql`, `mbstring`, `gd`, `exif`, `fileinfo`, `sodium`, `curl`, `openssl`.
  Si `gd` manque, les photos sont enregistrées **avec leur position GPS** : ne pas mettre en ligne sans elle.

---

## 2. Mettre le code sous Git

Le projet n'est pas encore un dépôt Git. Faites-le avant tout envoi sur le serveur, pour avoir un historique et une sauvegarde.

```powershell
cd C:\Users\alaae\lost-found-ai
git init
git add .
git status          # VÉRIFIER : config/config.php, storage/ et public/uploads/* ne doivent PAS apparaître
git commit -m "Version prête pour la mise en ligne"
```

- [ ] Créer un dépôt **privé** sur GitHub (`gh repo create lost-found-ai --private --source . --push`, ou via le site).
- [ ] Vérifier sur GitHub que `config/config.php` **n'y est pas**. Il contient votre clé secrète.

---

## 3. Base de données

- [ ] Dans hPanel > Bases de données > MySQL, **créer une base et un utilisateur**. Choisissez un mot de passe long et généré, et notez les trois valeurs : nom de la base, utilisateur, mot de passe.
  Chez Hostinger, les noms ont un préfixe, par exemple `u123456789_lostfound`.
- [ ] **Préparer le fichier d'import** : faites une copie de `database/schema.sql` et **supprimez les lignes 7 à 11** (`CREATE DATABASE ...` et `USE lost_found_ai;`). Sur un mutualisé, vous n'avez pas le droit de créer une base, et le nom n'est pas `lost_found_ai`.
- [ ] Ouvrir **phpMyAdmin**, sélectionner votre base, onglet *Importer*, puis envoyer la copie modifiée.
- [ ] Vérifier que les 12 tables sont présentes : `users`, `items`, `matches`, `conversations`, `messages`, `login_attempts`, `rate_limits`, `email_verifications`, `claims`, `orders`, `password_resets`, `reports`.

> Les fichiers de `database/migrations/` servent à mettre à jour une base **existante** (votre Docker local). Pour une installation neuve en production, `schema.sql` suffit.

---

## 4. Envoyer les fichiers

**Option A, recommandée** : la racine web du domaine pointe sur `public/`.
- [ ] Envoyer le projet dans un dossier **hors** de `public_html`, par exemple `/home/u123/lostfound/`, avec le gestionnaire de fichiers, FTP ou `git clone` en SSH.
- [ ] Dans hPanel, faire pointer le domaine vers `/home/u123/lostfound/public`. Si Hostinger ne le permet pas, passer à l'option B.

**Option B** : tout le projet dans `public_html/`.
- [ ] Envoyer tout le projet dans `public_html/`. Le fichier `.htaccess` à la racine renvoie alors toutes les requêtes vers `public/` et bloque le reste.

**Dans les deux cas :**
- [ ] **Ne pas envoyer** : `.git/`, votre `config/config.php` local, le contenu de `storage/` et de `public/uploads/`, `docker-compose.yml`, `Dockerfile`.
- [ ] Créer les dossiers `storage/proofs/` et `storage/logs/`, et vérifier que PHP peut écrire dans `storage/` et `public/uploads/` (droits 755).

---

## 5. Configuration de production

- [ ] Sur le serveur, **copier `config/config.example.php` en `config/config.php`**, puis remplacer les valeurs par défaut (à droite des `?:`) :

| Clé | Valeur en production |
|---|---|
| `app.url` | `https://votre-domaine.fr` (sans `/` final) |
| `app.debug` | `false`. **Ne jamais mettre `true` en ligne** |
| `app.key` | **une NOUVELLE clé**, différente de celle de votre PC : `php -r "echo bin2hex(random_bytes(32));"` |
| `db.host` / `db.name` / `db.user` / `db.password` | les valeurs de l'étape 3 (`host` vaut souvent `localhost` chez Hostinger) |
| `mail.transport` | `'smtp'` |
| `mail.host` / `port` / `encryption` / `username` / `password` | les valeurs de l'étape 7 |
| `mail.from` | `no-reply@votre-domaine.fr` |
| `delivery.enabled` | `false` (voir l'étape 13) |
| `legal.*` | les valeurs de l'étape 8 |

- [ ] **Sauvegarder `app.key` dans un gestionnaire de mots de passe.** Si cette clé est perdue, les adresses chiffrées et les liens signés deviennent inutilisables.
- [ ] Vérifier que `config/config.php` n'est pas lisible depuis le web : `https://votre-domaine.fr/config/config.php` doit afficher une **page 404**.

---

## 6. HTTPS

- [ ] hPanel > Sécurité > SSL : **installer le certificat gratuit** (Let's Encrypt).
- [ ] Activer **« Forcer HTTPS »**, pour que `http://` redirige vers `https://`.
- [ ] Contrôler que l'adresse affiche bien le cadenas. Le cookie de session passe alors en `Secure` et l'en-tête HSTS s'active tout seul.

---

## 7. Emails (indispensable : sans eux, personne ne peut finir son inscription)

- [ ] Créer un compte **Brevo** (gratuit jusqu'à 300 emails par jour), ou utiliser le SMTP de votre hébergeur.
- [ ] Dans Brevo, **ajouter et authentifier votre domaine**. Brevo donne des enregistrements DNS à ajouter dans la zone DNS de votre domaine (hPanel > Domaines > DNS) :
  - [ ] **SPF** (enregistrement TXT `v=spf1 include:spf.brevo.com ~all`, à fusionner avec un SPF existant s'il y en a un) ;
  - [ ] **DKIM** (enregistrement TXT fourni par Brevo) ;
  - [ ] **DMARC** (TXT sur `_dmarc` : `v=DMARC1; p=none; rua=mailto:vous@votre-domaine.fr`).
- [ ] Reporter les identifiants SMTP dans `config/config.php` : `smtp-relay.brevo.com`, port `587`, encryption `tls`, l'identifiant et la clé SMTP Brevo.
- [ ] Créer une **adresse de contact** (par exemple `contact@votre-domaine.fr`, une simple redirection vers votre Gmail suffit). Elle sert dans les mentions légales et reçoit les signalements.
- [ ] **Tester** : créer un compte sur le site avec une adresse de [mail-tester.com](https://www.mail-tester.com) et viser un score d'au moins 9/10. Tester aussi avec Gmail et Outlook, en vérifiant les spams.

---

## 8. Informations légales (vous seul pouvez les fournir)

Remplissez la section `legal` de `config/config.php` :

- [ ] `publisher_name` : votre nom, ou celui de votre structure.
- [ ] `publisher_status` : par exemple « particulier » ou « entrepreneur individuel ».
- [ ] `publisher_address` : adresse postale.
  > En tant que **particulier non professionnel**, la loi (LCEN, art. 6-III-2) permet de ne pas publier son adresse, à condition de l'avoir communiquée à l'hébergeur. Indiquez alors : « Coordonnées communiquées à l'hébergeur ». Si vous encaissez de l'argent, vous devenez professionnel et l'adresse devient obligatoire.
- [ ] `publisher_siret` : seulement si vous avez une micro-entreprise.
- [ ] `contact_email` : l'adresse de l'étape 7.
- [ ] `director` : le directeur ou la directrice de la publication, en général vous.
- [ ] `host_name`, `host_address`, `host_phone` : les coordonnées de l'hébergeur, à prendre dans **ses propres mentions légales** (pour Hostinger : Hostinger International Ltd, à Chypre).
- [ ] Ouvrir `/mentions-legales`, `/confidentialite` et `/cgu` : **aucun « [À COMPLÉTER] » orange ne doit rester**.
- [ ] **Relire les trois textes.** Ils correspondent à ce que fait le code, mais vous en êtes responsable. Si vous modifiez un texte, changez `UPDATED_AT` dans `src/Controllers/PageController.php`.
- [ ] RGPD : tenir un **registre des traitements**, un simple document. Le modèle de la CNIL pour les petites structures suffit, et la page `/confidentialite` en contient déjà le contenu.

---

## 9. Tâche planifiée (cron)

- [ ] hPanel > Avancé > Tâches Cron : ajouter une tâche **quotidienne**, par exemple à 4 h :
  ```
  /usr/bin/php /home/u123/lostfound/bin/cleanup.php
  ```
  Adaptez le chemin à votre dossier. Sans cette tâche, les comptes de robots et les journaux d'IP ne sont pas purgés, alors que la politique de confidentialité annonce 7 jours et 24 heures.

---

## 10. Compte administrateur

- [ ] Créer **votre compte** sur le site en ligne et valider l'email avec le code reçu.
- [ ] Lui donner le rôle d'administrateur :
  - avec SSH : `php bin/make-admin.php vous@exemple.fr` ;
  - sans SSH : dans phpMyAdmin, `UPDATE users SET role = 'admin' WHERE email = 'vous@exemple.fr';`
- [ ] Le lien **« Modération »** apparaît alors dans le menu, avec le nombre de signalements en attente.

---

## 11. Sauvegardes

- [ ] Activer les **sauvegardes automatiques** de l'hébergeur (quotidiennes ou hebdomadaires selon l'offre).
- [ ] En plus, une fois par semaine, faire **vos propres sauvegardes** :
  - export de la base (phpMyAdmin > Exporter) ;
  - téléchargement de `public/uploads/` (photos des annonces) et de `storage/proofs/` (photos de preuve) ;
  - et une fois pour toutes, une copie de `config/config.php` dans votre gestionnaire de mots de passe.
- [ ] **Tester une restauration** au moins une fois, par exemple en important la sauvegarde dans votre Docker local.

---

## 12. Recette : tests à faire sur le site en ligne

**Parcours utilisateur**
- [ ] Inscription : la case CGU est obligatoire, le code arrive par email (vérifier les spams).
- [ ] « Mot de passe oublié » : le lien arrive, fonctionne une seule fois, puis la connexion fonctionne avec le nouveau mot de passe.
- [ ] Publier une annonce **avec photo** prise au téléphone, la retélécharger et vérifier qu'elle n'a plus de position GPS (par exemple avec un vérificateur EXIF en ligne).
- [ ] Avec un second compte : publier l'annonce correspondante. Le score de correspondance s'affiche et l'email d'alerte arrive.
- [ ] Demande de restitution, puis acceptation : la conversation s'ouvre automatiquement et l'email « Organiser la remise » arrive.
- [ ] Signaler une annonce : l'email arrive à `contact_email` et l'annonce apparaît dans `/admin/reports`.
- [ ] Mon compte : l'export JSON se télécharge, puis la suppression du compte retire ses annonces et ses photos.

**Sécurité** (chaque adresse doit être refusée, avec une erreur 404 ou 403) :
- [ ] `https://votre-domaine.fr/config/config.php`
- [ ] `https://votre-domaine.fr/storage/`
- [ ] `https://votre-domaine.fr/bin/cleanup.php`
- [ ] `https://votre-domaine.fr/database/schema.sql`
- [ ] `https://votre-domaine.fr/.git/config`
- [ ] `http://votre-domaine.fr` redirige vers `https://`.
- [ ] Provoquer une erreur, par exemple `/items/999999` : le message doit être neutre, sans chemin de fichier ni requête SQL (preuve que `debug` est à `false`).
- [ ] Bonus : passer le site sur [securityheaders.com](https://securityheaders.com), en visant la note A.

---

## 13. Plus tard : activer la livraison payante

À ne faire qu'après le lancement, quand des utilisateurs le demandent. Tant que ces étapes ne sont pas faites, la livraison reste **automatiquement masquée** en production, même si `delivery.enabled` vaut `true`.

**Démarches**
- [ ] Créer une **micro-entreprise**. Encaisser des paiements est une activité commerciale : il faut un SIRET et des CGV.
- [ ] Ouvrir un compte **Stripe** et activer **Stripe Connect**. C'est obligatoire pour reverser le pourboire à la personne qui a trouvé l'objet : la plateforme n'a pas le droit de garder l'argent d'un tiers.
- [ ] Ouvrir un compte chez un **agrégateur de transport** (Boxtal ou Sendcloud) et récupérer la clé d'API.
- [ ] Ajouter des **CGV**, désigner un **médiateur de la consommation** (c'est obligatoire pour vendre à des particuliers), et mettre à jour les CGU.

**Code à écrire** (à faire une fois les clés obtenues) :
- [ ] Remplacer, dans `ShippingService`, les méthodes `quotes()`, `relayPoints()` et `createShipment()` par des appels à l'API Boxtal ou Sendcloud, puis passer `delivery.provider` à `'boxtal'`.
- [ ] Brancher Stripe Connect : compte connecté pour la personne qui a trouvé l'objet, versement du pourboire à la réception.
- [ ] Dans Stripe, déclarer le webhook `https://votre-domaine.fr/webhooks/stripe` et reporter `stripe_secret_key` et `stripe_webhook_secret` dans la configuration.
- [ ] Mettre `delivery.enabled` à `true`.

---

## 14. Optionnel mais utile

- [ ] **Surveillance** : [UptimeRobot](https://uptimerobot.com) (gratuit) vous envoie un email si le site tombe.
- [ ] **Google Search Console** : déclarer le domaine pour que les annonces soient indexées.
- [ ] Consulter de temps en temps le **journal d'erreurs PHP** (hPanel > Avancé > Journaux d'erreurs) : les erreurs y sont écrites même si elles ne s'affichent pas aux visiteurs.
- [ ] Préparer une **page ou une affiche de lancement** pour votre première cible B2B (campus, bibliothèque universitaire, salle de sport…).
