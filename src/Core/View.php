<?php
/**
 * View : affiche un gabarit PHP de src/Views à l'intérieur du layout commun.
 *
 * Exemple dans un contrôleur :
 *     View::render('auth/login', ['title' => 'Connexion', 'errors' => $errors]);
 *
 * Fonctionnement :
 *   1. les clés du tableau deviennent des variables ($title, $errors) ;
 *   2. src/Views/auth/login.php est exécuté, sa sortie est capturée dans $content ;
 *   3. src/Views/layout.php est affiché : il contient <html>, le menu, les
 *      messages flash, et insère $content au milieu.
 *
 * Dans les vues, toute donnée dynamique s'affiche avec e() : <?= e($title) ?>
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    private const VIEWS_DIR = __DIR__ . '/../Views/';

    public static function render(string $template, array $data = []): void
    {
        $templateFile = self::VIEWS_DIR . $template . '.php';
        if (!is_file($templateFile)) {
            throw new RuntimeException("Vue introuvable : {$template}");
        }

        // EXTR_SKIP : une clé ne peut pas écraser une variable déjà définie
        // (par exemple $templateFile).
        extract($data, EXTR_SKIP);

        // On capture le HTML de la vue au lieu de l'envoyer directement...
        ob_start();
        require $templateFile;
        $content = ob_get_clean();

        // ...puis on l'insère dans le layout, qui affiche $content.
        require self::VIEWS_DIR . 'layout.php';
    }

    /**
     * Génère un gabarit SANS le layout du site et retourne le HTML au lieu de
     * l'afficher. Utilisé pour le contenu des emails :
     *     $html = View::fetch('emails/verify_code', ['code' => '123456']);
     */
    public static function fetch(string $template, array $data = []): string
    {
        $templateFile = self::VIEWS_DIR . $template . '.php';
        if (!is_file($templateFile)) {
            throw new RuntimeException("Vue introuvable : {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $templateFile;

        return (string) ob_get_clean();
    }

    /**
     * Affiche une page d'erreur (404, 403, 405, 500...) avec le bon code HTTP.
     */
    public static function error(int $code, string $message): void
    {
        http_response_code($code);
        self::render('errors/error', [
            'title'   => 'Erreur ' . $code,
            'code'    => $code,
            'message' => $message,
        ]);
    }
}
