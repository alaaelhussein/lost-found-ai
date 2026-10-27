<?php
/**
 * Conditions générales d'utilisation (CGU).
 * Variables : $legal, $updatedAt, $delivery (livraison payante activée ?)
 *
 * Acceptées obligatoirement à l'inscription (case à cocher, date enregistrée
 * dans users.terms_accepted_at).
 */
?>
<article class="prose">
    <p class="eyebrow">Règles du service</p>
    <h1>Conditions générales d’utilisation</h1>
    <p class="prose-meta">Dernière mise à jour : <?= e($updatedAt) ?></p>

    <h2>1. Objet</h2>
    <p>
        Lost&amp;Found AI (le « Service ») permet de déclarer un objet perdu ou trouvé, de comparer
        automatiquement les annonces et de mettre en relation, de façon confidentielle, la personne
        qui a perdu un objet et celle qui l’a trouvé. Le Service est édité par
        <?= legal_value($legal['publisher_name']) ?> (voir les <a href="/mentions-legales">mentions légales</a>).
        L’utilisation du Service implique l’acceptation des présentes conditions.
    </p>

    <h2>2. Compte</h2>
    <ul>
        <li>L’inscription est gratuite et réservée aux personnes âgées d’au moins 15 ans.</li>
        <li>Vous devez fournir une adresse email valide, confirmée par un code.</li>
        <li>Vous êtes responsable de la confidentialité de votre mot de passe et des actions faites depuis votre compte.</li>
        <li>Vous pouvez supprimer votre compte à tout moment depuis la page <a href="/account">Mon compte</a>.</li>
    </ul>

    <h2>3. Règles de publication</h2>
    <p>Il est interdit de publier :</p>
    <ul>
        <li>un objet que vous n’avez pas réellement perdu ou trouvé, ou une annonce publicitaire ;</li>
        <li>des données personnelles visibles sur l’objet : <strong>ne photographiez jamais une pièce d’identité,
            une carte bancaire, un document administratif ou une clé de façon lisible</strong>. Décrivez-les sans
            montrer les informations qu’ils contiennent ;</li>
        <li>des contenus illicites, injurieux, discriminatoires ou portant atteinte aux droits d’autrui ;</li>
        <li>des objets dont la détention est illégale (armes, stupéfiants…).</li>
    </ul>
    <p>
        En publiant une photo, vous autorisez son affichage sur le Service pour la durée de l’annonce.
        Vous restez propriétaire de vos contenus.
    </p>

    <h2>4. Restitution d’un objet</h2>
    <ul>
        <li>La personne qui se dit propriétaire doit apporter des éléments de preuve. <strong>Une fausse
            déclaration de propriété peut constituer une escroquerie</strong> (article 313-1 du Code pénal).</li>
        <li>La personne qui a trouvé l’objet décide librement d’accepter ou de refuser une demande.</li>
        <?php if ($delivery): ?>
            <li>La restitution peut se faire en main propre ou par livraison. En cas de livraison, le propriétaire
                paie les frais de port et un pourboire fixe reversé à la personne qui a trouvé l’objet, une fois
                l’objet reçu.</li>
        <?php else: ?>
            <li>La restitution se fait en main propre. Le Service ne demande aucun paiement : méfiez-vous de
                toute personne qui exigerait de l’argent ou vos coordonnées bancaires.</li>
        <?php endif; ?>
        <li>Pour un rendez-vous, choisissez un lieu public et fréquenté, en journée.</li>
        <li>Le Service ne remplace pas les démarches officielles : un objet trouvé peut aussi être déposé au
            service des objets trouvés de la mairie ou au commissariat, et une pièce d’identité perdue doit être
            déclarée auprès des autorités.</li>
    </ul>

    <h2>5. Rôle et responsabilité du Service</h2>
    <p>
        Le Service est un intermédiaire technique : il n’est ni propriétaire, ni dépositaire des objets,
        et n’est pas partie aux échanges entre utilisateurs. Le score de correspondance est un calcul
        automatique indicatif, qui ne garantit pas que deux annonces concernent le même objet. Le Service
        s’efforce d’être disponible en permanence mais ne peut le garantir.
    </p>

    <h2>6. Modération</h2>
    <p>
        Tout utilisateur peut signaler une annonce. L’éditeur peut supprimer, sans préavis, une annonce
        ou un compte qui ne respecte pas les présentes conditions ou la loi.
    </p>

    <h2>7. Données personnelles</h2>
    <p>Voir la <a href="/confidentialite">politique de confidentialité</a>.</p>

    <h2>8. Modification des conditions</h2>
    <p>
        Ces conditions peuvent évoluer. La date de mise à jour figure en haut de la page ; en cas de
        changement important, les utilisateurs inscrits seront prévenus par email.
    </p>

    <h2>9. Droit applicable</h2>
    <p>
        Les présentes conditions sont soumises au droit français. En cas de litige, une solution amiable
        sera recherchée en priorité en écrivant à
        <?= $legal['contact_email'] !== null
            ? '<a href="mailto:' . e($legal['contact_email']) . '">' . e($legal['contact_email']) . '</a>'
            : legal_value(null) ?>.
    </p>
</article>
