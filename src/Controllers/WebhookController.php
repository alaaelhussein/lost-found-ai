<?php
/**
 * WebhookController : notifications envoyées par Stripe (serveur à serveur).
 *
 * Route : POST /webhooks/stripe
 *
 * Pas de jeton CSRF ici (ce n'est pas un formulaire de notre site) : la
 * protection est la SIGNATURE de Stripe, vérifiée par PaymentService avec le
 * secret du webhook. Une requête non signée ou mal signée est refusée (400).
 *
 * Configuration côté Stripe : Tableau de bord > Développeurs > Webhooks >
 * ajouter l'URL https://votre-site/webhooks/stripe, événement
 * « checkout.session.completed ». En local : stripe listen --forward-to
 * localhost:8000/webhooks/stripe (Stripe CLI).
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;

final class WebhookController
{
    /** POST /webhooks/stripe */
    public function stripe(): void
    {
        header('Content-Type: application/json');

        // Corps BRUT de la requête : la signature porte sur les octets exacts
        $payload = (string) file_get_contents('php://input');
        $event = (new PaymentService())->verifyWebhook($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));

        if ($event === null) {
            http_response_code(400);
            echo json_encode(['error' => 'signature invalide']);
            return;
        }

        if (($event['type'] ?? '') === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $orderId = (new Order())->findIdByPaymentRef((string) ($session['id'] ?? ''));
            $order = $orderId !== null ? (new Order())->findById($orderId) : null;

            // Double contrôle : session réellement payée ET montant identique à
            // celui de la commande (on ne fait confiance à aucune autre source).
            if ($order !== null
                && ($session['payment_status'] ?? '') === 'paid'
                && (int) ($session['amount_total'] ?? -1) === (int) $order['total_cents']
                && strtolower((string) ($session['currency'] ?? '')) === strtolower($order['currency'])
            ) {
                // Sans effet si déjà payée : Stripe peut envoyer le même événement plusieurs fois
                (new OrderService())->confirmPayment($orderId);
            }
        }

        // 200 = « bien reçu » : sinon Stripe renverrait l'événement plus tard
        echo json_encode(['received' => true]);
    }
}
