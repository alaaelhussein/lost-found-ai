<?php
/**
 * PaymentService : paiement de la livraison + pourboire.
 *
 * Deux fournisseurs :
 *   - 'simulation' (aucune clé Stripe configurée) : une page « Payer » fictive,
 *     pour développer et faire des démonstrations sans carte bancaire ;
 *   - 'stripe' (payments.stripe_secret_key renseignée) : vraie page de
 *     paiement Stripe Checkout. Les numéros de carte ne passent JAMAIS par
 *     notre serveur : l'utilisateur paie sur le site de Stripe, qui nous
 *     prévient ensuite par un « webhook » signé.
 *
 * Pourquoi un webhook ? Après le paiement, Stripe renvoie l'utilisateur sur
 * notre page de succès, mais cette redirection peut être falsifiée (il suffit
 * de taper l'URL). La seule preuve fiable est l'appel serveur à serveur que
 * Stripe envoie à /webhooks/stripe, signé avec un secret partagé.
 *
 * ⚠ Reverser le pourboire à la personne qui a trouvé l'objet = encaisser de
 *   l'argent pour le compte d'un tiers. Légalement, il faut passer par une
 *   solution de « marketplace » (Stripe Connect) : la plateforme ne garde
 *   pas l'argent elle-même. Voir README, section Livraison.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Order;
use RuntimeException;

final class PaymentService
{
    private const STRIPE_API = 'https://api.stripe.com/v1';
    /** Une signature de webhook plus vieille que 5 minutes est refusée (anti-rejeu). */
    private const WEBHOOK_TOLERANCE = 300;

    /** 'stripe' si une clé est configurée, sinon 'simulation'. */
    public function provider(): string
    {
        return Config::get('payments.stripe_secret_key', '') !== '' ? 'stripe' : 'simulation';
    }

    /**
     * Démarre le paiement d'une commande et retourne l'URL où envoyer
     * l'utilisateur : notre page de simulation, ou la page Stripe.
     */
    public function startCheckout(array $order, string $carrierName): string
    {
        if ($this->provider() === 'simulation') {
            return '/orders/' . $order['id'] . '/pay';
        }

        // Session Stripe Checkout : deux lignes sur le reçu (livraison + pourboire).
        // Les montants viennent de NOTRE base, jamais du navigateur.
        $session = $this->stripeRequest('/checkout/sessions', [
            'mode'                => 'payment',
            'client_reference_id' => (string) $order['id'],
            'metadata'            => ['order_id' => (string) $order['id']],
            'success_url'         => Notifier::url('/orders/' . $order['id'] . '?paiement=ok'),
            'cancel_url'          => Notifier::url('/orders/' . $order['id'] . '?paiement=annule'),
            'line_items'          => [
                [
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => 'eur',
                        'unit_amount'  => (int) $order['shipping_cents'],
                        'product_data' => ['name' => 'Livraison ' . $carrierName . ' – « ' . $order['item_title'] . ' »'],
                    ],
                ],
                [
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => 'eur',
                        'unit_amount'  => (int) $order['tip_cents'],
                        'product_data' => ['name' => 'Pourboire pour la personne qui a trouvé votre objet'],
                    ],
                ],
            ],
        ]);

        (new Order())->setPaymentRef((int) $order['id'], (string) $session['id']);

        return (string) $session['url'];
    }

    /**
     * Vérifie et décode un webhook Stripe. Retourne l'événement, ou null si la
     * signature est absente, fausse ou trop ancienne.
     *
     * En-tête reçu : « Stripe-Signature: t=1700000000,v1=5257a8... »
     * Signature attendue = HMAC-SHA256( t + "." + corps brut , secret du webhook )
     */
    public function verifyWebhook(string $payload, string $signatureHeader): ?array
    {
        $secret = (string) Config::get('payments.stripe_webhook_secret', '');
        if ($secret === '' || $signatureHeader === '') {
            return null;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $event = json_decode($payload, true);

                return is_array($event) ? $event : null;
            }
        }

        return null;
    }

    /**
     * Appel à l'API Stripe avec curl. Stripe attend des données de formulaire
     * (line_items[0][price_data][currency]=eur...) : http_build_query les
     * construit à partir du tableau PHP imbriqué.
     */
    private function stripeRequest(string $path, array $params): array
    {
        $curl = curl_init(self::STRIPE_API . $path);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . Config::get('payments.stripe_secret_key')],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);

        $data = is_string($body) ? json_decode($body, true) : null;
        if ($status !== 200 || !is_array($data)) {
            // On ne montre jamais le détail à l'utilisateur, seulement dans le log
            throw new RuntimeException('Stripe : erreur ' . $status . ' ' . ($data['error']['message'] ?? $error));
        }

        return $data;
    }
}
