<?php
/**
 * Mailer : envoi des emails de l'application.
 *
 *     Mailer::send('alice@mail.com', 'Sujet', '<p>HTML</p>', 'Version texte');
 *
 * Deux modes (config mail.transport) :
 *   - 'smtp' : connexion à un serveur SMTP. En local, Docker fournit Mailpit,
 *              une fausse boîte mail qui capture tout : http://localhost:8025
 *   - 'log'  : aucun envoi, l'email est écrit dans storage/logs/mail.log
 *
 * Pourquoi un client SMTP écrit à la main ? Le projet n'utilise pas Composer
 * (donc pas PHPMailer). La fonction mail() de PHP dépend d'un sendmail
 * configuré sur le serveur, rarement présent sous Windows/Docker. Le protocole
 * SMTP est simple : une suite de commandes texte, chacune suivie d'un code
 * réponse à 3 chiffres (2xx/3xx = OK, 4xx/5xx = erreur).
 *
 * Un échec d'envoi ne doit jamais faire planter la page : send() retourne
 * false et écrit l'erreur dans le log PHP.
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class Mailer
{
    private const TIMEOUT = 10; // secondes

    /** @var resource|null Connexion SMTP en cours */
    private static $socket = null;

    /**
     * Envoie un email (HTML + version texte). Retourne true si accepté.
     */
    public static function send(string $to, string $subject, string $html, string $text): bool
    {
        // Protection contre l'injection d'en-têtes : une adresse ou un sujet
        // contenant un retour à la ligne pourrait ajouter des destinataires cachés.
        if (preg_match('/[\r\n]/', $to . $subject) || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            error_log('Mailer : destinataire ou sujet invalide, email ignoré.');
            return false;
        }

        $message = self::buildMessage($to, $subject, $html, $text);

        try {
            if (Config::get('mail.transport', 'log') === 'smtp') {
                self::sendSmtp($to, $message);
            } else {
                self::writeLog($to, $subject, $text);
            }

            return true;
        } catch (Throwable $e) {
            error_log('Mailer : échec de l\'envoi à ' . $to . ' : ' . $e->getMessage());
            self::close();

            return false;
        }
    }

    /**
     * Construit le message au format MIME : en-têtes + deux versions du
     * contenu (texte brut et HTML). Le logiciel de messagerie affiche la
     * version HTML s'il le peut, sinon la version texte.
     */
    private static function buildMessage(string $to, string $subject, string $html, string $text): string
    {
        $from = (string) Config::get('mail.from', 'no-reply@lostfound.local');
        $fromName = (string) Config::get('mail.from_name', 'Lost&Found AI');
        $boundary = 'lf_' . bin2hex(random_bytes(12)); // séparateur entre les deux versions
        $domain = substr(strrchr($from, '@') ?: '@lostfound.local', 1);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            // Les en-têtes n'acceptent que l'ASCII : les accents sont encodés en base64
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . '--' . $boundary . "--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /** « Vérification » => =?UTF-8?B?VsOpcmlmaWNhdGlvbg==?= (format RFC 2047) */
    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    // ------------------------------------------------------------------
    // Transport SMTP
    // ------------------------------------------------------------------

    /**
     * Dialogue SMTP complet. Exemple d'échange (S = serveur, C = client) :
     *   S: 220 mail.example.com prêt
     *   C: EHLO votre-domaine.fr        S: 250 ...
     *   C: STARTTLS                     S: 220 (puis chiffrement de la connexion)
     *   C: AUTH LOGIN ...               S: 235 authentifié
     *   C: MAIL FROM:<no-reply@...>     S: 250
     *   C: RCPT TO:<alice@...>          S: 250
     *   C: DATA                         S: 354 (on envoie le message, terminé par « . »)
     *   C: QUIT                         S: 221
     */
    /**
     * Nom sous lequel on se présente au serveur SMTP (commande EHLO) : le
     * domaine du site (app.url). Les filtres anti-spam pénalisent un nom
     * inventé comme « lostfound.local ». Repli sur localhost en développement.
     */
    private static function heloDomain(): string
    {
        $host = (string) parse_url((string) Config::get('app.url', ''), PHP_URL_HOST);

        // Un nom de domaine ne contient que lettres, chiffres, points et tirets :
        // on refuse tout le reste (pas de risque d'injecter une commande SMTP).
        return preg_match('/^[A-Za-z0-9.-]+$/', $host) ? $host : 'localhost';
    }

    private static function sendSmtp(string $to, string $message): void
    {
        $host = (string) Config::get('mail.host');
        $port = (int) Config::get('mail.port', 587);
        $encryption = (string) Config::get('mail.encryption', '');

        // 'ssl' (port 465) : connexion chiffrée dès le départ
        $address = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($address, $errno, $errstr, self::TIMEOUT);
        if ($socket === false) {
            throw new RuntimeException("connexion à {$address} impossible ({$errstr})");
        }
        stream_set_timeout($socket, self::TIMEOUT);
        self::$socket = $socket;

        self::expect(220);
        $ehlo = 'EHLO ' . self::heloDomain();
        self::command($ehlo, 250);

        // 'tls' (port 587) : la connexion démarre en clair puis passe en chiffré
        if ($encryption === 'tls') {
            self::command('STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('échec du passage en TLS');
            }
            self::command($ehlo, 250); // on se présente à nouveau, en chiffré
        }

        $username = (string) Config::get('mail.username', '');
        if ($username !== '') {
            self::command('AUTH LOGIN', 334);
            self::command(base64_encode($username), 334);
            self::command(base64_encode((string) Config::get('mail.password', '')), 235);
        }

        self::command('MAIL FROM:<' . Config::get('mail.from') . '>', 250);
        self::command('RCPT TO:<' . $to . '>', [250, 251]);
        self::command('DATA', 354);

        // « Dot-stuffing » : une ligne qui commence par « . » est doublée, car
        // une ligne contenant seulement « . » signifie « fin du message ».
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $message));
        self::command($data . "\r\n.", 250);

        self::command('QUIT', 221);
        self::close();
    }

    /**
     * Envoie une commande et vérifie le code de la réponse.
     *
     * @param int|list<int> $expected
     */
    private static function command(string $line, $expected): void
    {
        fwrite(self::$socket, $line . "\r\n");
        self::expect($expected);
    }

    /**
     * Lit la réponse du serveur. Une réponse peut tenir sur plusieurs lignes :
     * « 250-... » (tiret = la suite arrive) puis « 250 ... » (espace = fin).
     *
     * @param int|list<int> $expected
     */
    private static function expect($expected): void
    {
        $response = '';
        while (($line = fgets(self::$socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            throw new RuntimeException('réponse SMTP inattendue : ' . trim($response));
        }
    }

    private static function close(): void
    {
        if (is_resource(self::$socket)) {
            fclose(self::$socket);
        }
        self::$socket = null;
    }

    // ------------------------------------------------------------------
    // Transport « log » (sans serveur mail)
    // ------------------------------------------------------------------

    private static function writeLog(string $to, string $subject, string $text): void
    {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $entry = str_repeat('=', 70) . "\n"
            . date('Y-m-d H:i:s') . "\nÀ : {$to}\nSujet : {$subject}\n\n{$text}\n\n";
        file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
