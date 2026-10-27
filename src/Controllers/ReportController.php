<?php
/**
 * ReportController : signaler une annonce à la modération.
 *
 * Route :
 *   POST /items/{id}/report   motif + précisions facultatives
 *
 * Réservé aux comptes vérifiés (un robot ne peut pas inonder la modération)
 * et limité à 10 signalements par heure et par compte. L'équipe reçoit un
 * email (legal.contact_email) et traite le signalement sur /admin/reports.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Throttle;
use App\Core\View;
use App\Models\Item;
use App\Models\Report;
use App\Services\Notifier;

final class ReportController
{
    private const DETAILS_MAX = 500;

    /** POST /items/{id}/report */
    public function store(int $itemId): void
    {
        Auth::requireVerified();
        Csrf::verify();
        Throttle::check('report', 'user:' . Auth::id(), 10, 3600);

        $item = (new Item())->findById($itemId);
        if ($item === null) {
            View::error(404, 'Annonce introuvable.');
            return;
        }
        if ((int) $item['user_id'] === (int) Auth::id()) {
            Flash::add('info', 'Vous ne pouvez pas signaler votre propre annonce : supprimez-la depuis « Mes annonces ».');
            redirect('/items/' . $itemId);
        }

        $reason = (string) ($_POST['reason'] ?? '');
        if (!isset(Report::REASONS[$reason])) {
            Flash::add('error', 'Choisissez un motif de signalement.');
            redirect('/items/' . $itemId . '#signaler');
        }

        $details = trim((string) ($_POST['details'] ?? ''));
        $details = $details === '' ? null : mb_substr($details, 0, self::DETAILS_MAX);

        if ((new Report())->create($itemId, (int) Auth::id(), $reason, $details)) {
            (new Notifier())->reportReceived($item, Report::REASONS[$reason], $details);
            Flash::add('success', 'Merci, votre signalement a été transmis à l’équipe de modération.');
        } else {
            Flash::add('info', 'Vous avez déjà signalé cette annonce. L’équipe de modération va l’examiner.');
        }

        redirect('/items/' . $itemId);
    }
}
