<?php
/**
 * Notifier : tous les emails envoyés par l'application, au même endroit.
 *
 * Chaque méthode correspond à un événement :
 *   verificationCode  code de vérification à l'inscription
 *   passwordReset     lien « mot de passe oublié »
 *   passwordChanged   alerte de sécurité : le mot de passe vient d'être changé
 *   reportReceived    une annonce a été signalée (email à l'équipe de modération)
 *   newMatches        une nouvelle annonce correspond à la vôtre
 *   claimReceived     quelqu'un pense que l'objet que vous avez trouvé est à lui
 *   claimDecided      votre demande de restitution est acceptée / refusée
 *   orderPaid         livraison payée (étiquette pour l'expéditeur, confirmation pour l'acheteur)
 *   orderShipped      colis déposé, numéro de suivi
 *   orderDelivered    colis reçu, pourboire disponible
 *
 * Le contenu de chaque email est un gabarit PHP dans src/Views/emails/,
 * inséré dans emails/layout.php. La version texte (pour les logiciels qui
 * n'affichent pas le HTML) est déduite automatiquement du HTML.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Crypto;
use App\Core\Mailer;
use App\Core\View;
use App\Models\MatchModel;

final class Notifier
{
    // ------------------------------------------------------------------
    // Compte
    // ------------------------------------------------------------------

    public function verificationCode(array $user, string $code): bool
    {
        return $this->send($user['email'], 'Votre code de vérification : ' . $code, 'verify_code', [
            'name'    => $user['name'],
            'code'    => $code,
            'minutes' => EmailVerifier::VALID_MINUTES,
            'link'    => self::url('/verify-email'),
        ]);
    }

    /** @param string $token Jeton EN CLAIR (seul son HMAC est en base) */
    public function passwordReset(array $user, string $token, int $minutes): bool
    {
        return $this->send($user['email'], 'Choisissez un nouveau mot de passe', 'password_reset', [
            'name'    => $user['name'],
            'link'    => self::url('/reset-password?token=' . $token),
            'minutes' => $minutes,
        ]);
    }

    /**
     * Alerte envoyée après un changement de mot de passe depuis « Mon compte » :
     * si ce n'était pas le titulaire, il est prévenu et peut réagir.
     */
    public function passwordChanged(array $user): bool
    {
        return $this->send($user['email'], 'Votre mot de passe a été modifié', 'password_changed', [
            'name' => $user['name'],
            'link' => self::url('/forgot-password'),
        ]);
    }

    // ------------------------------------------------------------------
    // Modération
    // ------------------------------------------------------------------

    /**
     * Prévient l'équipe de modération (legal.contact_email) qu'une annonce a
     * été signalée. Rien n'est envoyé si aucune adresse n'est configurée.
     */
    public function reportReceived(array $item, string $reasonLabel, ?string $details): bool
    {
        $to = (string) Config::get('legal.contact_email', '');
        if ($to === '') {
            return false;
        }

        return $this->send($to, 'Annonce signalée : ' . $item['title'], 'report_received', [
            'itemTitle' => $item['title'],
            'reason'    => $reasonLabel,
            'details'   => $details,
            'itemLink'  => self::url('/items/' . $item['id']),
            'adminLink' => self::url('/admin/reports'),
        ]);
    }

    // ------------------------------------------------------------------
    // Correspondances
    // ------------------------------------------------------------------

    /**
     * Prévient les auteurs des annonces qui correspondent à $item (sauf
     * s'ils ont désactivé ces emails ou n'ont pas vérifié leur adresse).
     * Chaque correspondance est marquée « notifiée » pour ne jamais être
     * envoyée deux fois.
     */
    public function newMatches(array $item): void
    {
        $matches = new MatchModel();
        $minScore = (int) Config::get('matching.min_score', 60);

        foreach ($matches->pendingNotifications((int) $item['id'], (string) $item['type'], $minScore) as $row) {
            if ((int) $row['notify_matches'] === 1 && $row['email_verified_at'] !== null) {
                $this->send($row['owner_email'], 'Une annonce correspond à la vôtre (' . $row['score'] . ' %)', 'new_match', [
                    'name'          => $row['owner_name'],
                    'score'         => (int) $row['score'],
                    'yourTitle'     => $row['other_title'],
                    'yourType'      => $row['other_type'],
                    'matchTitle'    => $item['title'],
                    'link'          => self::url('/items/' . $row['other_item_id'] . '#correspondances'),
                    'unsubscribe'   => self::unsubscribeUrl((int) $row['owner_id']),
                ]);
            }
            $matches->markNotified((int) $row['match_id']);
        }
    }

    // ------------------------------------------------------------------
    // Demandes de restitution
    // ------------------------------------------------------------------

    /** @param array $claim Résultat de Claim::findById */
    public function claimReceived(array $claim): bool
    {
        return $this->send($claim['finder_email'], 'Quelqu’un pense que l’objet trouvé est à lui', 'claim_received', [
            'name'      => $claim['finder_name'],
            'claimant'  => $claim['claimant_name'],
            'itemTitle' => $claim['item_title'],
            'link'      => self::url('/items/' . $claim['item_id'] . '#demandes'),
        ]);
    }

    /**
     * @param int|null $conversationId Conversation ouverte pour une remise en
     *                                 main propre (livraison désactivée), sinon null
     */
    public function claimDecided(array $claim, bool $approved, ?int $conversationId = null): bool
    {
        return $this->send(
            $claim['claimant_email'],
            $approved ? 'Votre demande est acceptée : récupérez votre objet' : 'Votre demande de restitution a été refusée',
            'claim_decided',
            [
                'name'      => $claim['claimant_name'],
                'approved'  => $approved,
                'itemTitle' => $claim['item_title'],
                'conversationLink' => $conversationId === null ? null : self::url('/conversations/' . $conversationId),
                'orderLink' => self::url('/claims/' . $claim['id'] . '/order'),
                'itemLink'  => self::url('/items/' . $claim['item_id']),
                'tip'       => money((int) Config::get('delivery.tip_cents', 500)),
            ]
        );
    }

    // ------------------------------------------------------------------
    // Livraison
    // ------------------------------------------------------------------

    /** @param array $order Résultat de Order::findById */
    public function orderPaid(array $order, string $carrierName): void
    {
        $common = [
            'itemTitle' => $order['item_title'],
            'carrier'   => $carrierName,
            'tracking'  => $order['tracking_number'],
            'orderLink' => self::url('/orders/' . $order['id']),
        ];

        // À la personne qui a trouvé l'objet : étiquette et instructions de dépôt
        $this->send($order['finder_email'], 'Livraison payée : déposez le colis', 'order_paid_finder', $common + [
            'name'       => $order['finder_name'],
            'labelLink'  => self::url('/orders/' . $order['id'] . '/label'),
            'tip'        => money((int) $order['tip_cents']),
        ]);

        // Au propriétaire : confirmation du paiement
        $this->send($order['buyer_email'], 'Paiement confirmé pour « ' . $order['item_title'] . ' »', 'order_paid_buyer', $common + [
            'name'  => $order['buyer_name'],
            'total' => money((int) $order['total_cents']),
        ]);
    }

    public function orderShipped(array $order, string $carrierName): bool
    {
        return $this->send($order['buyer_email'], 'Votre objet est en route', 'order_shipped', [
            'name'      => $order['buyer_name'],
            'itemTitle' => $order['item_title'],
            'carrier'   => $carrierName,
            'tracking'  => $order['tracking_number'],
            'orderLink' => self::url('/orders/' . $order['id']),
        ]);
    }

    public function orderDelivered(array $order): bool
    {
        return $this->send($order['finder_email'], 'Objet bien reçu : merci !', 'order_delivered', [
            'name'      => $order['finder_name'],
            'buyer'     => $order['buyer_name'],
            'itemTitle' => $order['item_title'],
            'tip'       => money((int) $order['tip_cents']),
            'orderLink' => self::url('/orders/' . $order['id']),
        ]);
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    /** Lien absolu vers le site (les emails ne peuvent pas contenir de liens relatifs). */
    public static function url(string $path): string
    {
        return rtrim((string) Config::get('app.url', 'http://localhost:8000'), '/') . $path;
    }

    /**
     * Lien de désinscription signé : il contient l'id du compte et une
     * signature HMAC. Sans la clé secrète, impossible de fabriquer le lien
     * d'un autre compte en changeant l'id.
     */
    public static function unsubscribeUrl(int $userId): string
    {
        return self::url('/notifications/unsubscribe?u=' . $userId . '&s=' . Crypto::sign('unsubscribe:' . $userId));
    }

    /** Génère l'email à partir de son gabarit et l'envoie. */
    private function send(string $to, string $subject, string $template, array $data): bool
    {
        $content = View::fetch('emails/' . $template, $data);
        $html = View::fetch('emails/layout', ['subject' => $subject, 'content' => $content]);

        return Mailer::send($to, $subject, $html, self::htmlToText($content));
    }

    /**
     * Version texte d'un email HTML : les liens deviennent « texte (adresse) »,
     * les paragraphes des retours à la ligne, puis on retire les balises.
     */
    private static function htmlToText(string $html): string
    {
        $text = preg_replace('/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);
        $text = preg_replace('/<(br|\/p|\/h1|\/h2|\/li|\/div|\/tr)[^>]*>/i', "\n", (string) $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);

        return trim((string) preg_replace("/\n\s*\n\s*\n+/", "\n\n", (string) $text));
    }
}
