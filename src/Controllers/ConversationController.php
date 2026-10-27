<?php
/**
 * ConversationController : messagerie privée entre deux utilisateurs liés
 * par une annonce perdue et une annonce trouvée.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Throttle;
use App\Core\View;
use App\Models\Conversation;

final class ConversationController
{
    public const MESSAGE_MAX = 2000;

    /** GET /conversations */
    public function index(): void
    {
        Auth::requireLogin();
        View::render('conversations/index', [
            'title' => 'Messages',
            'conversations' => (new Conversation())->findAllForUser((int) Auth::id()),
        ]);
    }

    /** GET /conversations/{id} */
    public function show(int $id): void
    {
        Auth::requireLogin();
        $conversation = (new Conversation())->findForUser($id, (int) Auth::id());
        if ($conversation === null) {
            View::error(404, 'Conversation introuvable ou accès refusé.');
            return;
        }

        View::render('conversations/show', [
            'title' => 'Conversation',
            'conversation' => $conversation,
            'messages' => (new Conversation())->messages($id),
        ]);
    }

    /** POST /conversations/{id}/messages */
    public function send(int $id): void
    {
        Auth::requireVerified();
        Csrf::verify();
        // Anti-spam : 20 messages maximum par tranche de 5 minutes
        Throttle::check('message', 'user:' . Auth::id(), 20, 300);

        $conversation = new Conversation();
        if ($conversation->findForUser($id, (int) Auth::id()) === null) {
            View::error(404, 'Conversation introuvable ou accès refusé.');
            return;
        }

        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > self::MESSAGE_MAX) {
            Flash::add('error', 'Le message doit contenir entre 1 et 2000 caractères.');
            redirect('/conversations/' . $id);
        }

        $conversation->addMessage($id, (int) Auth::id(), $content);
        redirect('/conversations/' . $id);
    }
}
