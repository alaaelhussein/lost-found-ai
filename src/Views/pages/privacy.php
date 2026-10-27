<?php
/**
 * Politique de confidentialité (RGPD, articles 12 à 14 : information des personnes).
 * Variables : $legal, $updatedAt, $delivery (livraison payante activée ?)
 *
 * Chaque durée de conservation indiquée ici correspond à ce que fait réellement
 * le code (voir bin/cleanup.php, Crypto, PhotoStorage...). Si le code change,
 * cette page doit changer aussi : c'est une obligation légale, pas un texte décoratif.
 */
?>
<article class="prose">
    <p class="eyebrow">Vos données</p>
    <h1>Politique de confidentialité</h1>
    <p class="prose-meta">Dernière mise à jour : <?= e($updatedAt) ?></p>

    <p>
        Lost&amp;Found AI aide à retrouver le propriétaire d’un objet perdu. Pour cela, nous traitons
        un minimum de données personnelles. Cette page explique lesquelles, pourquoi, combien de temps,
        et comment exercer vos droits.
    </p>

    <h2>1. Responsable du traitement</h2>
    <p>
        <?= legal_value($legal['publisher_name']) ?>, <?= legal_value($legal['publisher_address']) ?>.<br>
        Contact pour toute question sur vos données :
        <?= $legal['contact_email'] !== null
            ? '<a href="mailto:' . e($legal['contact_email']) . '">' . e($legal['contact_email']) . '</a>'
            : legal_value(null) ?>
    </p>

    <h2>2. Données collectées et finalités</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th scope="col">Données</th><th scope="col">Pourquoi</th><th scope="col">Base légale</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Nom ou pseudo, email, mot de passe (stocké uniquement sous forme chiffrée irréversible)</td>
                    <td>Créer et sécuriser votre compte, vous envoyer les codes et alertes</td>
                    <td>Exécution du contrat (CGU)</td>
                </tr>
                <tr>
                    <td>Annonces : titre, description, catégorie, couleur, lieu, date, photo</td>
                    <td>Publier l’annonce et calculer les correspondances</td>
                    <td>Exécution du contrat</td>
                </tr>
                <tr>
                    <td>Messages échangés sur la messagerie interne</td>
                    <td>Permettre la restitution sans dévoiler vos coordonnées</td>
                    <td>Exécution du contrat</td>
                </tr>
                <tr>
                    <td>Demandes de restitution : réponses, preuves, photo de preuve</td>
                    <td>Vérifier que l’objet vous appartient</td>
                    <td>Exécution du contrat</td>
                </tr>
                <?php if ($delivery): ?>
                    <tr>
                        <td>Livraison : nom du destinataire, point relais ou adresse, montant payé</td>
                        <td>Expédier l’objet et rémunérer la personne qui l’a trouvé</td>
                        <td>Exécution du contrat</td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>Adresse IP, tentatives de connexion, nombre d’actions récentes</td>
                    <td>Bloquer les robots, le spam et les tentatives de piratage</td>
                    <td>Intérêt légitime (sécurité du service)</td>
                </tr>
                <tr>
                    <td>Signalements d’annonces</td>
                    <td>Modérer les contenus illicites ou frauduleux</td>
                    <td>Obligation légale et intérêt légitime</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p>
        <strong>Ce que nous ne faisons pas :</strong> aucune publicité, aucune revente de données,
        aucun outil de mesure d’audience ou de pistage. Les photos sont réenregistrées à leur
        réception, ce qui efface les métadonnées (dont la position GPS de la prise de vue).
    </p>

    <h2>3. Qui voit vos données ?</h2>
    <ul>
        <li><strong>Les autres utilisateurs</strong> voient votre nom ou pseudo et vos annonces. Votre email n’est jamais affiché.</li>
        <li><strong>La personne qui a trouvé l’objet</strong> voit les preuves que vous lui envoyez dans une demande de restitution.</li>
        <li><strong>Nos prestataires techniques</strong>, uniquement pour faire fonctionner le service : l’hébergeur
            (<?= legal_value($legal['host_name']) ?>) et le service d’envoi des emails<?= $delivery ? ', le prestataire de paiement (Stripe) et le transporteur choisi' : '' ?>.</li>
        <li><strong>Les autorités</strong>, sur réquisition judiciaire uniquement.</li>
    </ul>

    <h2>4. Durées de conservation</h2>
    <ul>
        <li>Compte, annonces, messages et demandes : jusqu’à la suppression de votre compte.</li>
        <li>Compte créé mais jamais confirmé par le code reçu par email : supprimé automatiquement après 7 jours.</li>
        <li>Code de vérification : 15 minutes. Lien « mot de passe oublié » : 1 heure.</li>
        <li>Adresses IP et journaux de sécurité : 24 heures.</li>
        <?php if ($delivery): ?>
            <li>Adresse de livraison à domicile : chiffrée, puis effacée dès la réception du colis.
                Les données de paiement sont conservées par Stripe, jamais par nous.</li>
        <?php endif; ?>
    </ul>

    <h2>5. Vos droits</h2>
    <p>Vous disposez des droits d’accès, de rectification, d’effacement, de limitation, d’opposition et de portabilité. Concrètement :</p>
    <ul>
        <li><strong>Télécharger vos données</strong> et <strong>supprimer votre compte</strong> : depuis la page <a href="/account">Mon compte</a>, en un clic ;</li>
        <li><strong>Couper les emails d’alerte</strong> : depuis Mon compte ou le lien présent dans chaque alerte ;</li>
        <li>pour toute autre demande, écrivez à l’adresse de contact ci-dessus : nous répondons sous un mois.</li>
    </ul>
    <p>
        Si vous estimez que vos droits ne sont pas respectés, vous pouvez adresser une réclamation à la
        CNIL (<a href="https://www.cnil.fr/fr/plaintes" rel="noopener" target="_blank">cnil.fr/plaintes</a>).
    </p>

    <h2>6. Cookies</h2>
    <p>
        Le site utilise un seul cookie, <code>lfai_session</code>, indispensable pour rester connecté
        et protéger les formulaires. Il est supprimé à la fermeture du navigateur. Aucun cookie
        publicitaire ou de mesure d’audience n’est utilisé : c’est pourquoi aucun bandeau de
        consentement ne vous est demandé.
    </p>

    <h2>7. Sécurité</h2>
    <p>
        Connexion chiffrée (HTTPS), mots de passe hachés, photos de preuve stockées hors de l’espace
        public, limitation des tentatives de connexion, adresses de livraison chiffrées. Aucun système
        n’étant infaillible, nous vous informerons dans les délais légaux en cas de violation de vos données.
    </p>
</article>
