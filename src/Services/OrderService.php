<?php
/**
 * OrderService : étapes d'une commande de livraison et leurs conséquences.
 *
 * Chaque étape est déclenchée par un contrôleur (clic d'un utilisateur) ou
 * par le webhook Stripe, mais la logique est ici, à un seul endroit :
 *
 *   confirmPayment  paid      => numéro de suivi, annonce « en cours de restitution »,
 *                                emails aux deux personnes
 *   markShipped     shipped   => email au propriétaire avec le suivi
 *   markDelivered   delivered => annonce résolue, pourboire disponible, email de remerciement
 *
 * Chaque méthode retourne false si la commande n'était pas dans l'état
 * attendu : rien n'est alors modifié et aucun email n'est envoyé (protection
 * contre les doubles clics et les webhooks reçus deux fois).
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\Order;

final class OrderService
{
    public function confirmPayment(int $orderId): bool
    {
        $orders = new Order();
        $shipping = new ShippingService();

        if (!$orders->markPaid($orderId, $shipping->createShipment($orderId))) {
            return false; // déjà payée
        }

        $order = $orders->findById($orderId);
        // L'objet n'accepte plus de nouvelles demandes : il est en route
        (new Item())->setStatus((int) $order['item_id'], 'matched');
        (new Notifier())->orderPaid($order, $shipping->carrierName($order['carrier']));

        return true;
    }

    public function markShipped(int $orderId): bool
    {
        $orders = new Order();
        if (!$orders->markShipped($orderId)) {
            return false;
        }

        $order = $orders->findById($orderId);
        (new Notifier())->orderShipped($order, (new ShippingService())->carrierName($order['carrier']));

        return true;
    }

    public function markDelivered(int $orderId): bool
    {
        $orders = new Order();
        if (!$orders->markDelivered($orderId)) {
            return false;
        }

        $order = $orders->findById($orderId);
        (new Item())->setStatus((int) $order['item_id'], 'resolved');
        (new Notifier())->orderDelivered($order);

        return true;
    }
}
