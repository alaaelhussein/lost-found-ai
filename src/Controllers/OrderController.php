<?php
/**
 * OrderController : commande de livraison d'un objet trouvé.
 *
 * Routes :
 *   GET  /claims/{id}/order    choix du mode, du transporteur, du point relais
 *   POST /claims/{id}/order    création de la commande puis paiement
 *   GET  /orders/{id}          suivi (propriétaire ou personne qui expédie)
 *   GET  /orders/{id}/pay      page de paiement SIMULÉ (développement)
 *   POST /orders/{id}/pay      confirmation du paiement simulé
 *   GET  /orders/{id}/label    étiquette à imprimer (personne qui expédie)
 *   POST /orders/{id}/shipped  « J'ai déposé le colis »
 *   POST /orders/{id}/delivered « J'ai bien reçu mon objet »
 *
 * Règles :
 *   - seul le demandeur d'une demande ACCEPTÉE peut commander, une fois ;
 *   - le prix est recalculé côté serveur à partir du transporteur choisi :
 *     un montant envoyé par le navigateur serait falsifiable ;
 *   - l'adresse à domicile est chiffrée (Crypto) et effacée à la livraison.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Throttle;
use App\Core\View;
use App\Models\Claim;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ShippingService;
use Throwable;

final class OrderController
{
    /** GET /claims/{id}/order?mode=relay&zip=75001 */
    public function create(int $claimId): void
    {
        $claim = $this->orderableClaim($claimId);
        if ($claim === null) {
            return;
        }

        $mode = (string) ($_GET['mode'] ?? 'relay');
        $zip = trim((string) ($_GET['zip'] ?? ''));
        $this->renderForm($claim, (new ShippingService())->isMode($mode) ? $mode : 'relay', $zip, [], []);
    }

    /** POST /claims/{id}/order */
    public function store(int $claimId): void
    {
        Csrf::verify();
        $claim = $this->orderableClaim($claimId);
        if ($claim === null) {
            return;
        }
        Throttle::check('order', 'user:' . Auth::id(), 10, 3600);

        $shipping = new ShippingService();
        $mode = (string) ($_POST['mode'] ?? '');
        $zip = trim((string) ($_POST['zip'] ?? ''));
        $carrier = (string) ($_POST['carrier'] ?? '');
        $name = trim((string) ($_POST['recipient_name'] ?? ''));
        $old = $_POST;
        $errors = [];

        if (!$shipping->isMode($mode)) {
            $mode = 'relay';
            $errors['mode'] = 'Choisissez un mode de livraison.';
        }
        // Le prix vient de NOTRE grille, jamais du formulaire
        $quote = $shipping->quote($mode, $carrier);
        if ($quote === null) {
            $errors['carrier'] = 'Choisissez un transporteur.';
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['recipient_name'] = $mode === 'home'
                ? 'Indiquez le nom et le prénom du destinataire.'
                : 'Indiquez au moins votre prénom (affiché au point relais pour retirer le colis).';
        }

        $relayId = null;
        $relayLabel = null;
        $encryptedAddress = null;

        if ($mode === 'relay' || $mode === 'locker') {
            // Le point choisi doit exister pour ce code postal (vérifié côté serveur)
            $points = $shipping->relayPoints($mode, $zip);
            $relayId = (string) ($_POST['relay_point'] ?? '');
            if (!isset($points[$relayId])) {
                $errors['relay_point'] = 'Cherchez votre code postal puis choisissez un point de retrait.';
            } else {
                $relayLabel = $points[$relayId];
            }
        } else {
            $street = trim((string) ($_POST['street'] ?? ''));
            $city = trim((string) ($_POST['city'] ?? ''));
            if ($street === '' || mb_strlen($street) > 200) {
                $errors['street'] = 'Indiquez votre adresse.';
            }
            if (!preg_match('/^\d{5}$/', $zip)) {
                $errors['zip'] = 'Code postal à 5 chiffres.';
            }
            if ($city === '' || mb_strlen($city) > 100) {
                $errors['city'] = 'Indiquez votre ville.';
            }
            if ($errors === []) {
                // Chiffrée AVANT d'être enregistrée : jamais en clair dans la base
                $encryptedAddress = Crypto::encrypt(json_encode(
                    ['street' => $street, 'zip' => $zip, 'city' => $city],
                    JSON_UNESCAPED_UNICODE
                ));
            }
        }

        if (($_POST['accept'] ?? '') !== '1') {
            $errors['accept'] = 'Acceptez le récapitulatif pour continuer.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($claim, $mode, $zip, $errors, $old);
            return;
        }

        $tip = (int) Config::get('delivery.tip_cents', 500);
        $payments = new PaymentService();
        $orders = new Order();

        $orderId = $orders->create([
            'claim_id'          => $claimId,
            'buyer_id'          => (int) Auth::id(),
            'finder_id'         => (int) $claim['finder_id'],
            'carrier'           => $carrier,
            'delivery_mode'     => $mode,
            'relay_point_id'    => $relayId,
            'relay_point_label' => $relayLabel,
            'recipient_name'    => $name,
            'address_encrypted' => $encryptedAddress,
            'shipping_cents'    => $quote['cents'],
            'tip_cents'         => $tip,
            'total_cents'       => $quote['cents'] + $tip,
            'payment_provider'  => $payments->provider(),
        ]);

        try {
            redirect($payments->startCheckout($orders->findById($orderId), $shipping->carrierName($carrier)));
        } catch (Throwable $e) {
            error_log((string) $e);
            Flash::add('error', 'Le paiement n’a pas pu démarrer. Réessayez depuis la page de la commande.');
            redirect('/orders/' . $orderId);
        }
    }

    /** GET /orders/{id} */
    public function show(int $id): void
    {
        $order = $this->accessibleOrder($id);
        if ($order === null) {
            return;
        }

        // Retour de la page Stripe : la confirmation définitive arrive par webhook
        if (($_GET['paiement'] ?? '') === 'ok' && $order['status'] === 'pending_payment') {
            Flash::add('info', 'Paiement reçu par Stripe, confirmation en cours. Actualisez la page dans quelques secondes.');
        } elseif (($_GET['paiement'] ?? '') === 'annule') {
            Flash::add('info', 'Paiement annulé. Vous pouvez réessayer quand vous voulez.');
        }

        View::render('orders/show', [
            'title'       => 'Livraison de « ' . $order['item_title'] . ' »',
            'order'       => $order,
            'isBuyer'     => (int) $order['buyer_id'] === (int) Auth::id(),
            'carrierName' => (new ShippingService())->carrierName($order['carrier']),
            'canPay'      => $order['status'] === 'pending_payment',
        ]);
    }

    /** GET /orders/{id}/pay : le propriétaire relance ou effectue le paiement */
    public function payForm(int $id): void
    {
        $order = $this->payableOrder($id);
        if ($order === null) {
            return;
        }

        $payments = new PaymentService();
        if ($payments->provider() === 'stripe') {
            // Paiement interrompu : on recrée une session Stripe
            redirect($payments->startCheckout($order, (new ShippingService())->carrierName($order['carrier'])));
        }

        View::render('orders/pay', [
            'title'       => 'Paiement',
            'order'       => $order,
            'carrierName' => (new ShippingService())->carrierName($order['carrier']),
        ]);
    }

    /** POST /orders/{id}/pay : paiement SIMULÉ (impossible si Stripe est configuré) */
    public function pay(int $id): void
    {
        Csrf::verify();
        $order = $this->payableOrder($id);
        if ($order === null) {
            return;
        }
        if ((new PaymentService())->provider() !== 'simulation' || $order['payment_provider'] !== 'simulation') {
            View::error(403, 'Le paiement simulé est désactivé : utilisez Stripe.');
            return;
        }

        (new OrderService())->confirmPayment($id);
        Flash::add('success', 'Paiement confirmé (simulation). La personne qui a trouvé votre objet a reçu l’étiquette d’envoi.');
        redirect('/orders/' . $id);
    }

    /** GET /orders/{id}/label : étiquette, pour la personne qui expédie */
    public function label(int $id): void
    {
        $order = $this->accessibleOrder($id);
        if ($order === null) {
            return;
        }
        if ((int) $order['finder_id'] !== (int) Auth::id() || !in_array($order['status'], ['paid', 'shipped'], true)) {
            View::error(403, 'L’étiquette est disponible pour l’expéditeur, une fois la livraison payée.');
            return;
        }

        // Domicile : l'adresse est déchiffrée uniquement pour l'imprimer
        $address = null;
        if ($order['delivery_mode'] === 'home' && $order['address_encrypted'] !== null) {
            $address = json_decode((string) Crypto::decrypt($order['address_encrypted']), true);
        }

        View::render('orders/label', [
            'title'       => 'Étiquette d’envoi',
            'order'       => $order,
            'address'     => $address,
            'carrierName' => (new ShippingService())->carrierName($order['carrier']),
            'sender'      => (string) Config::get('delivery.sender', 'Lost&Found AI'),
        ]);
    }

    /** POST /orders/{id}/shipped */
    public function shipped(int $id): void
    {
        Csrf::verify();
        $order = $this->accessibleOrder($id);
        if ($order === null) {
            return;
        }
        if ((int) $order['finder_id'] !== (int) Auth::id() || !(new OrderService())->markShipped($id)) {
            View::error(422, 'Action impossible à cette étape de la commande.');
            return;
        }

        Flash::add('success', 'Merci ! Le propriétaire est prévenu que son objet est en route.');
        redirect('/orders/' . $id);
    }

    /** POST /orders/{id}/delivered */
    public function delivered(int $id): void
    {
        Csrf::verify();
        $order = $this->accessibleOrder($id);
        if ($order === null) {
            return;
        }
        if ((int) $order['buyer_id'] !== (int) Auth::id() || !(new OrderService())->markDelivered($id)) {
            View::error(422, 'Action impossible à cette étape de la commande.');
            return;
        }

        Flash::add('success', 'Super, vous avez retrouvé votre objet ! Le pourboire est transmis à la personne qui l’a trouvé.');
        redirect('/orders/' . $id);
    }

    // ------------------------------------------------------------------
    // Contrôles d'accès
    // ------------------------------------------------------------------

    /**
     * Demande pour laquelle l'utilisateur connecté peut commander une livraison.
     * S'il existe déjà une commande, on y redirige.
     */
    private function orderableClaim(int $claimId): ?array
    {
        Auth::requireVerified();
        if (!$this->deliveryAvailable()) {
            return null;
        }
        $claim = (new Claim())->findById($claimId);

        if ($claim === null || (int) $claim['claimant_id'] !== (int) Auth::id()) {
            View::error(404, 'Demande introuvable ou accès refusé.');
            return null;
        }
        if ($claim['status'] !== 'approved') {
            View::error(422, 'La livraison est possible une fois votre demande acceptée.');
            return null;
        }
        if ($claim['order_id'] !== null) {
            redirect('/orders/' . $claim['order_id']);
        }

        return $claim;
    }

    /**
     * Commander et payer ne sont possibles que si la livraison est activée
     * (voir ShippingService::isEnabled). Le suivi d'une commande déjà payée,
     * l'étiquette et les confirmations d'envoi / de réception restent
     * accessibles même si la fonctionnalité est coupée entre-temps.
     */
    private function deliveryAvailable(): bool
    {
        if (ShippingService::isEnabled()) {
            return true;
        }

        View::error(404, 'La livraison n’est pas encore disponible : organisez la remise en main propre par la messagerie, dans un lieu public.');
        return false;
    }

    /** Commande visible par l'utilisateur connecté (acheteur ou expéditeur). */
    private function accessibleOrder(int $id): ?array
    {
        Auth::requireLogin();
        $order = (new Order())->findById($id);
        $me = (int) Auth::id();

        if ($order === null || ($me !== (int) $order['buyer_id'] && $me !== (int) $order['finder_id'])) {
            View::error(404, 'Commande introuvable ou accès refusé.');
            return null;
        }

        return $order;
    }

    /** Commande que l'utilisateur connecté doit encore payer. */
    private function payableOrder(int $id): ?array
    {
        if (!$this->deliveryAvailable()) {
            return null;
        }
        $order = $this->accessibleOrder($id);
        if ($order === null) {
            return null;
        }
        if ((int) $order['buyer_id'] !== (int) Auth::id() || $order['status'] !== 'pending_payment') {
            redirect('/orders/' . $id);
        }

        return $order;
    }

    private function renderForm(array $claim, string $mode, string $zip, array $errors, array $old): void
    {
        $shipping = new ShippingService();
        View::render('orders/create', [
            'title'       => 'Livraison de votre objet',
            'claim'       => $claim,
            'mode'        => $mode,
            'zip'         => $zip,
            'modes'       => ShippingService::MODES,
            'quotes'      => $shipping->quotes($mode),
            'relayPoints' => $shipping->relayPoints($mode, $zip),
            'tipCents'    => (int) Config::get('delivery.tip_cents', 500),
            'isSimulated' => (new PaymentService())->provider() === 'simulation',
            'errors'      => $errors,
            'old'         => $old,
        ]);
    }
}
