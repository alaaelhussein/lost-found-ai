<?php
/**
 * AccountService : suppression définitive d'un compte (droit à l'effacement,
 * article 17 du RGPD).
 *
 * Utilisée à trois endroits :
 *   - « Mon compte » > « Supprimer mon compte » (AccountController) ;
 *   - la modération, pour bannir un compte malveillant (AdminController) ;
 *   - le nettoyage automatique des comptes jamais vérifiés (bin/cleanup.php).
 *
 * Deux étapes :
 *   1. la ligne users est supprimée : les clés étrangères ON DELETE CASCADE
 *      effacent en même temps annonces, messages, demandes, commandes... ;
 *   2. les FICHIERS liés (photos d'annonces publiques, photos de preuve
 *      privées) sont supprimés du disque : la base ne peut pas le faire
 *      elle-même, et une photo publique resterait accessible par son URL.
 *
 * Garde-fou : impossible de supprimer un compte qui a une livraison payée en
 * cours (colis en route, pourboire à verser) : l'autre personne perdrait le
 * suivi de son objet et de son argent. Il faut attendre la réception.
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Claim;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;

final class AccountService
{
    /**
     * Raison pour laquelle le compte ne peut pas encore être supprimé, ou null
     * si la suppression est possible.
     */
    public function blockingReason(int $userId): ?string
    {
        if ((new Order())->countActiveForUser($userId) > 0) {
            return 'Une livraison payée est en cours sur votre compte. Vous pourrez le supprimer une fois l’objet reçu.';
        }

        return null;
    }

    /**
     * Supprime le compte, ses données et ses fichiers. À n'appeler qu'après
     * blockingReason() === null (sauf modération d'un compte frauduleux).
     */
    public function delete(int $userId): void
    {
        // On relève les fichiers AVANT la suppression : après, les lignes qui
        // indiquent leurs noms n'existent plus.
        $publicPhotos = (new Item())->photoPathsByUser($userId);
        $proofPhotos = (new Claim())->proofPhotosLinkedToUser($userId);

        (new User())->delete($userId);

        // Les fichiers sont effacés seulement une fois la base à jour : si la
        // suppression SQL échouait (exception), aucune photo ne serait perdue.
        $public = PhotoStorage::publicPhotos();
        foreach ($publicPhotos as $path) {
            $public->delete($path);
        }
        $private = PhotoStorage::privateProofs();
        foreach ($proofPhotos as $path) {
            $private->delete($path);
        }
    }
}
