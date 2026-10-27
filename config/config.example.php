<?php
/**
 * Modèle de configuration.
 *
 * Installation : copier ce fichier en config/config.php puis adapter les
 * valeurs (mot de passe MySQL, mode debug...).
 * config/config.php est exclu du versionnement (.gitignore) car il contient
 * des secrets : ne jamais le committer.
 *
 * Chaque valeur peut venir d'une variable d'environnement (getenv) : c'est ce
 * que fait docker-compose.yml en local. Sur un hébergement mutualisé sans
 * variables d'environnement (Hostinger...), remplacez directement les valeurs
 * par défaut à droite des « ?: ». Liste complète : DEPLOIEMENT.md.
 *
 * Lecture dans le code : Config::get('db.host'), Config::get('app.debug')...
 */

return [
    'app' => [
        'name'     => 'Lost&Found AI',
        // Adresse publique du site, SANS « / » final. Sert aux liens des emails
        // et au nom présenté au serveur SMTP. Production : https://votre-domaine.fr
        'url'      => getenv('APP_URL') ?: 'http://localhost:8000',
        // true : les erreurs détaillées s'affichent à l'écran (développement).
        // false par défaut : c'est la valeur OBLIGATOIRE en production (les
        // détails d'une erreur révéleraient chemins, requêtes SQL...).
        'debug'    => getenv('APP_DEBUG') === '1',
        'timezone' => 'Europe/Paris',
        // Clé secrète de l'application (64 caractères hexadécimaux). Elle sert à :
        //   - chiffrer les adresses de livraison en base (App\Core\Crypto) ;
        //   - signer les liens d'emails (désinscription) et les codes de vérification.
        // Générer une clé : php -r "echo bin2hex(random_bytes(32));"
        // Ne JAMAIS la changer en production : les adresses chiffrées deviendraient illisibles.
        'key'      => getenv('APP_KEY') ?: '',
    ],

    // Envoi des emails (App\Core\Mailer)
    //   transport 'smtp' : envoi réel via un serveur SMTP
    //       - en local avec Docker : Mailpit (boîte de test, http://localhost:8025)
    //       - en production : Brevo, OVH, Gmail... (host, port 587, user, password)
    //   transport 'log'  : rien n'est envoyé, les emails sont écrits dans
    //                      storage/logs/mail.log (pratique sans Docker)
    'mail' => [
        'transport'  => getenv('MAIL_HOST') ? 'smtp' : 'log',
        'host'       => getenv('MAIL_HOST') ?: '',
        'port'       => (int) (getenv('MAIL_PORT') ?: 587),
        'encryption' => getenv('MAIL_ENCRYPTION') ?: '',   // '', 'tls' (STARTTLS, port 587) ou 'ssl' (port 465)
        'username'   => getenv('MAIL_USERNAME') ?: '',
        'password'   => getenv('MAIL_PASSWORD') ?: '',
        'from'       => getenv('MAIL_FROM') ?: 'no-reply@lostfound.local',
        'from_name'  => 'Lost&Found AI',
    ],

    // Livraison d'un objet trouvé à son propriétaire (App\Services\ShippingService)
    'delivery' => [
        // Interrupteur général de la livraison payante. false : restitution en
        // main propre uniquement (la messagerie s'ouvre quand une demande est
        // acceptée). En production, même à true, la livraison reste masquée tant
        // que Stripe ET un vrai transporteur ne sont pas branchés
        // (voir ShippingService::isEnabled).
        'enabled'   => getenv('DELIVERY_ENABLED') === '1',
        'tip_cents' => 500,   // pourboire fixe pour la personne qui a trouvé l'objet (5 €)
        // 'simulation' : transporteurs et tarifs indicatifs, étiquettes fictives (développement).
        // En production : brancher un agrégateur (Boxtal, Sendcloud) qui donne accès à
        // Colissimo, Chronopost, Mondial Relay, InPost... avec une seule API.
        'provider'  => 'simulation',
        // Adresse d'expédition imprimée sur les étiquettes : celle de la plateforme,
        // pour que la personne qui a trouvé l'objet reste anonyme.
        'sender'    => 'Lost&Found AI – Service retours, 75000 Paris',
    ],

    // Informations affichées sur les pages légales (PageController) : mentions
    // légales, confidentialité, CGU. Une valeur vide s'affiche « [À COMPLÉTER] ».
    // contact_email reçoit aussi les signalements d'annonces (modération).
    'legal' => [
        'publisher_name'    => getenv('LEGAL_PUBLISHER_NAME') ?: '',    // ex. « Alaa EL HUSSEIN »
        'publisher_status'  => getenv('LEGAL_PUBLISHER_STATUS') ?: '',  // ex. « particulier » ou « entrepreneur individuel »
        'publisher_address' => getenv('LEGAL_PUBLISHER_ADDRESS') ?: '', // adresse postale (ou domiciliation)
        'publisher_siret'   => getenv('LEGAL_PUBLISHER_SIRET') ?: '',   // facultatif tant qu'il n'y a pas d'activité commerciale
        'contact_email'     => getenv('LEGAL_CONTACT_EMAIL') ?: '',     // ex. contact@votre-domaine.fr
        'director'          => getenv('LEGAL_DIRECTOR') ?: '',          // directeur de la publication (souvent vous-même)
        'host_name'         => getenv('LEGAL_HOST_NAME') ?: '',         // ex. « Hostinger International Ltd »
        'host_address'      => getenv('LEGAL_HOST_ADDRESS') ?: '',
        'host_phone'        => getenv('LEGAL_HOST_PHONE') ?: '',
    ],

    // Paiement (App\Services\PaymentService)
    //   sans clé Stripe : paiement SIMULÉ (bouton « Payer » fictif), pour le développement ;
    //   avec clé Stripe : vraie page de paiement Stripe Checkout (mode test : clés sk_test_...).
    'payments' => [
        'stripe_secret_key'     => getenv('STRIPE_SECRET_KEY') ?: '',
        'stripe_webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
    ],

    'db' => [
        // Docker Compose fournit ces variables à l'application. Les valeurs
        // par défaut restent compatibles avec une installation locale classique.
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('DB_PORT') ?: 3306),
        'name'     => getenv('DB_NAME') ?: 'lost_found_ai',
        'user'     => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',   // gère tous les caractères, y compris les emojis
    ],

    // Utilisé à l'étape « annonces » (upload des photos)
    'uploads' => [
        'dir'           => __DIR__ . '/../public/uploads',
        'max_size'      => 3 * 1024 * 1024, // 3 Mo
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    // Utilisé par MatchingService
    'matching' => [
        'min_score' => 60, // seuil d'affichage des correspondances (sur 100)
    ],
];
