<?php
/**
 * Fonctions utilitaires globales, disponibles partout (contrôleurs et vues).
 * Chargées par src/bootstrap.php.
 */

declare(strict_types=1);

/**
 * Échappe une valeur avant de l'afficher dans du HTML (protection XSS).
 *
 * À utiliser pour TOUTE donnée venant d'un utilisateur ou de la base :
 *     <p><?= e($item['title']) ?></p>
 *
 * ENT_QUOTES échappe aussi les guillemets simples et doubles, ce qui rend la
 * fonction sûre à l'intérieur d'un attribut : value="<?= e($x) ?>".
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirige vers une autre page et arrête le script.
 *
 * Utilisée après chaque formulaire traité avec succès (modèle
 * « Post/Redirect/Get ») : si l'utilisateur rafraîchit la page,
 * le formulaire n'est pas renvoyé une deuxième fois.
 */
function redirect(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

/**
 * Adresse IP du visiteur.
 *
 * On lit uniquement REMOTE_ADDR. Les en-têtes comme X-Forwarded-For sont
 * envoyés par le client et peuvent être falsifiés : ils permettraient de
 * contourner la limite de tentatives de connexion.
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Attributs d'accessibilité d'un champ de formulaire en erreur.
 *
 *     <input id="email" name="email" <?= field_attrs($errors, 'email') ?>>
 *
 * aria-invalid signale le champ comme invalide aux lecteurs d'écran, et
 * aria-describedby leur fait lire le message d'erreur (voir field_error) quand
 * le champ reçoit le focus. Si $hintId est donné, l'aide du champ est lue aussi.
 */
function field_attrs(array $errors, string $name, ?string $hintId = null): string
{
    $describedBy = array_filter([$hintId, isset($errors[$name]) ? $name . '-error' : null]);
    $attrs = $describedBy === [] ? '' : ' aria-describedby="' . e(implode(' ', $describedBy)) . '"';

    return isset($errors[$name]) ? $attrs . ' aria-invalid="true"' : $attrs;
}

/**
 * Message d'erreur affiché sous un champ, relié au champ par field_attrs().
 */
function field_error(array $errors, string $name): string
{
    if (!isset($errors[$name])) {
        return '';
    }

    return '<p class="field-error" id="' . e($name) . '-error">' . e($errors[$name]) . '</p>';
}

/**
 * Valeur de configuration affichée dans les pages légales (déjà échappée).
 * Si elle n'est pas encore renseignée (null), affiche un marqueur bien visible
 * pour ne pas mettre le site en ligne avec des mentions incomplètes.
 *
 *     <?= legal_value($legal['host_name']) ?>
 */
function legal_value(?string $value): string
{
    return $value === null ? '<mark class="todo">[À COMPLÉTER]</mark>' : e($value);
}

/**
 * Bouton d'action dans un email (styles inline : voir emails/layout.php).
 */
function email_button(string $url, string $label): string
{
    return '<p style="margin:24px 0;"><a href="' . e($url) . '" style="display:inline-block;padding:12px 22px;'
        . 'border-radius:10px;background:#4f46e5;color:#ffffff;font-weight:bold;text-decoration:none;">'
        . e($label) . '</a></p>';
}

/**
 * Montant en centimes => texte en euros : 949 => « 9,49 € ».
 */
function money(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ') . ' €';
}

/**
 * Icône SVG en ligne (tracés inspirés de la bibliothèque libre Lucide).
 *
 *     <?= icon('map-pin') ?> Bibliothèque
 *
 * Les icônes sont décoratives (aria-hidden) : le texte à côté porte le sens.
 * Elles prennent la couleur du texte (currentColor) et s'adaptent au mode sombre.
 */
function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'package'  => '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'map-pin'  => '<path d="M20 10c0 5-8 12-8 12s-8-7-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'message'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'sparkles' => '<path d="M12 3 9.5 9.5 3 12l6.5 2.5L12 21l2.5-6.5L21 12l-6.5-2.5Z"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'arrow-left'  => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'trash'    => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
        'rotate'   => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
        'send'     => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
    ];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
}
