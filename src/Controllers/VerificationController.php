<?php
/**
 * VerificationController : vérification de l'email et désinscription des alertes.
 *
 * Routes :
 *   GET  /verify-email                  formulaire de saisie du code
 *   POST /verify-email                  vérification du code
 *   POST /verify-email/resend           envoi d'un nouveau code
 *   GET  /notifications/unsubscribe     page de désinscription (lien signé reçu par email)
 *   POST /notifications/unsubscribe     confirmation de la désinscription
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Throttle;
use App\Core\View;
use App\Models\User;
use App\Services\EmailVerifier;

final class VerificationController
{
    /** GET /verify-email */
    public function show(): void
    {
        Auth::requireLogin();
        if (Auth::isVerified()) {
            redirect('/');
        }

        $this->render([]);
    }

    /** POST /verify-email */
    public function verify(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        // Limite par compte EN PLUS des 5 essais par code : empêche d'enchaîner
        // « nouveau code / 5 essais » en boucle pour deviner.
        Throttle::check('verify_code', 'user:' . Auth::id(), 15, 3600);

        // On ne garde que les chiffres : « 123 456 » ou « 123-456 » sont acceptés
        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        if (strlen((string) $code) !== 6) {
            http_response_code(422);
            $this->render(['code' => 'Le code contient 6 chiffres.']);
            return;
        }

        $result = (new EmailVerifier())->check((int) Auth::id(), (string) $code);

        if ($result === EmailVerifier::OK) {
            Flash::add('success', 'Votre adresse email est confirmée. Votre compte est activé !');
            redirect(Auth::pullIntendedUrl('/'));
        }

        $messages = [
            EmailVerifier::WRONG    => 'Code incorrect. Vérifiez l’email reçu et réessayez.',
            EmailVerifier::EXPIRED  => 'Ce code a expiré. Demandez-en un nouveau.',
            EmailVerifier::TOO_MANY => 'Trop d’essais avec ce code. Demandez-en un nouveau.',
        ];
        http_response_code(422);
        $this->render(['code' => $messages[$result]]);
    }

    /** POST /verify-email/resend */
    public function resend(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        if (Auth::isVerified()) {
            redirect('/');
        }
        Throttle::check('verify_resend', 'user:' . Auth::id(), 5, 3600);

        $verifier = new EmailVerifier();
        $wait = $verifier->secondsBeforeResend((int) Auth::id());
        if ($wait > 0) {
            Flash::add('info', 'Patientez ' . $wait . ' secondes avant de demander un nouveau code.');
            redirect('/verify-email');
        }

        if ($verifier->sendCode(Auth::user())) {
            Flash::add('success', 'Un nouveau code a été envoyé à ' . Auth::user()['email'] . '.');
        } else {
            Flash::add('error', 'L’email n’a pas pu être envoyé. Réessayez dans quelques minutes.');
        }
        redirect('/verify-email');
    }

    /** GET /notifications/unsubscribe?u=12&s=signature */
    public function unsubscribeForm(): void
    {
        [$userId, $signature] = $this->unsubscribeParams($_GET);

        // Page de confirmation plutôt que désinscription immédiate : certains
        // antivirus et messageries « cliquent » sur les liens pour les analyser.
        View::render('notifications/unsubscribe', [
            'title'     => 'Se désinscrire',
            'valid'     => $userId !== null,
            'userId'    => $userId,
            'signature' => $signature,
            'done'      => false,
        ]);
    }

    /** POST /notifications/unsubscribe */
    public function unsubscribe(): void
    {
        Csrf::verify();
        [$userId] = $this->unsubscribeParams($_POST);

        if ($userId !== null) {
            (new User())->setNotifyMatches($userId, false);
        }

        View::render('notifications/unsubscribe', [
            'title' => 'Se désinscrire',
            'valid' => $userId !== null,
            'done'  => true,
        ]);
    }

    /**
     * Lit et vérifie l'id + la signature d'un lien de désinscription.
     *
     * @return array{0: ?int, 1: string} [id du compte si la signature est valide, signature]
     */
    private function unsubscribeParams(array $source): array
    {
        $userId = (int) ($source['u'] ?? 0);
        $signature = (string) ($source['s'] ?? '');
        $valid = $userId > 0 && Crypto::verify('unsubscribe:' . $userId, $signature);

        return [$valid ? $userId : null, $signature];
    }

    private function render(array $errors): void
    {
        $verifier = new EmailVerifier();
        View::render('auth/verify', [
            'title'       => 'Vérifiez votre email',
            'email'       => Auth::user()['email'],
            'errors'      => $errors,
            'resendWait'  => $verifier->secondsBeforeResend((int) Auth::id()),
        ]);
    }
}
