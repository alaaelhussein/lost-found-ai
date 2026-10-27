<?php
/**
 * Mentions légales (obligatoires : loi n° 2004-575 du 21 juin 2004, dite LCEN, article 6-III).
 * Variables : $legal (section 'legal' de la configuration), $updatedAt, $siteUrl
 *
 * Les valeurs non renseignées s'affichent « [À COMPLÉTER] » (voir legal_value).
 */
?>
<article class="prose">
    <p class="eyebrow">Informations légales</p>
    <h1>Mentions légales</h1>
    <p class="prose-meta">Dernière mise à jour : <?= e($updatedAt) ?></p>

    <h2>Éditeur du site</h2>
    <p>
        Le site <?= e($siteUrl) ?> (« Lost&amp;Found AI ») est édité par :<br>
        <strong><?= legal_value($legal['publisher_name']) ?></strong>, <?= legal_value($legal['publisher_status']) ?><br>
        Adresse : <?= legal_value($legal['publisher_address']) ?><br>
        <?php if ($legal['publisher_siret'] !== null): ?>
            SIRET : <?= e($legal['publisher_siret']) ?><br>
        <?php endif; ?>
        Contact : <?= $legal['contact_email'] !== null
            ? '<a href="mailto:' . e($legal['contact_email']) . '">' . e($legal['contact_email']) . '</a>'
            : legal_value(null) ?>
    </p>
    <p>Directeur ou directrice de la publication : <?= legal_value($legal['director']) ?></p>

    <h2>Hébergement</h2>
    <p>
        Le site est hébergé par :<br>
        <strong><?= legal_value($legal['host_name']) ?></strong><br>
        <?= legal_value($legal['host_address']) ?><br>
        Téléphone : <?= legal_value($legal['host_phone']) ?>
    </p>

    <h2>Contenus publiés par les utilisateurs</h2>
    <p>
        Les annonces, photos et messages sont publiés sous la seule responsabilité de leurs auteurs.
        En tant qu’hébergeur de ces contenus, l’éditeur n’en effectue pas de contrôle préalable mais
        retire promptement tout contenu manifestement illicite qui lui est signalé, via le bouton
        « Signaler cette annonce » ou à l’adresse de contact ci-dessus.
    </p>

    <h2>Propriété intellectuelle</h2>
    <p>
        La structure du site, ses textes, son logo et son code sont la propriété de l’éditeur.
        Toute reproduction sans autorisation est interdite. Les utilisateurs conservent leurs droits
        sur les photos et textes qu’ils publient (voir les <a href="/cgu">conditions d’utilisation</a>).
    </p>

    <h2>Données personnelles</h2>
    <p>
        Le traitement de vos données est décrit dans la <a href="/confidentialite">politique de confidentialité</a>.
    </p>
</article>
