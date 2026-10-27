<?php
/**
 * PhotoStorage : enregistrement sécurisé des photos envoyées par les utilisateurs.
 *
 * Utilisé à deux endroits, avec deux dossiers différents :
 *   - photos des annonces    : public/uploads/   (PUBLIQUES, servies par le serveur web)
 *   - photos de preuve       : storage/proofs/   (PRIVÉES, hors de public/, servies
 *                              uniquement par ClaimController après contrôle d'accès)
 *
 *     $storage = PhotoStorage::publicPhotos();     // ou PhotoStorage::privateProofs()
 *     $name = $storage->store($_FILES['photo'] ?? null, $errors);   // null si aucune photo
 *     $storage->delete($name);
 *
 * Contrôles appliqués à chaque fichier (voir README, section Sécurité) :
 *   1. erreur d'envoi et taille maximale (3 Mo) ;
 *   2. type RÉEL du fichier lu dans son contenu (finfo), pas son extension ;
 *   3. dimensions lues dans l'en-tête, limitées AVANT décodage (images « bombes ») ;
 *   4. nom aléatoire (impossible à deviner, aucune extension dangereuse) ;
 *   5. réencodage avec GD : suppression des métadonnées EXIF/GPS.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use finfo;

final class PhotoStorage
{
    /** Au-delà (largeur ou hauteur), l'image est refusée : son décodage saturerait la mémoire. */
    private const MAX_PIXELS = 6000;
    /** Côté le plus long de la photo enregistrée (redimensionnée si plus grande). */
    private const STORED_MAX = 1600;

    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * @param string $directory   Dossier de destination sur le disque
     * @param string $returnPrefix Préfixe du chemin retourné ('uploads/' pour les photos publiques)
     */
    public function __construct(private string $directory, private string $returnPrefix = '')
    {
    }

    /** Photos des annonces, publiques : public/uploads/ */
    public static function publicPhotos(): self
    {
        return new self((string) Config::get('uploads.dir'), 'uploads/');
    }

    /** Photos de preuve de propriété, privées : storage/proofs/ (hors de public/) */
    public static function privateProofs(): self
    {
        return new self(__DIR__ . '/../../storage/proofs');
    }

    /** Chemin complet d'un fichier enregistré (pour le servir après contrôle d'accès). */
    public function path(string $stored): string
    {
        // basename() : on reste dans le dossier, même si la valeur en base était
        // altérée (« ../../config/config.php » devient « config.php »).
        return $this->directory . DIRECTORY_SEPARATOR . basename($stored);
    }

    /**
     * Vérifie et enregistre une photo envoyée par formulaire.
     *
     * @param array|null            $file   Élément de $_FILES
     * @param array<string, string> $errors Reçoit ['photo' => message] en cas d'erreur
     * @return string|null Chemin à stocker en base, ou null (pas de photo ou erreur)
     */
    public function store(?array $file, array &$errors): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors['photo'] = 'La photo n’a pas pu être envoyée.';
            return null;
        }

        $maxSize = (int) Config::get('uploads.max_size', 3 * 1024 * 1024);
        if (($file['size'] ?? 0) > $maxSize || !is_uploaded_file($file['tmp_name'] ?? '')) {
            $errors['photo'] = 'La photo doit faire 3 Mo maximum.';
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = Config::get('uploads.allowed_mimes', []);
        if (!isset(self::EXTENSIONS[$mime]) || !in_array($mime, $allowed, true)) {
            $errors['photo'] = 'Format accepté : JPG, PNG ou WebP.';
            return null;
        }

        // getimagesize lit seulement l'en-tête : on vérifie que c'est une vraie
        // image et qu'elle n'est pas gigantesque AVANT de la décoder (une image
        // de 30 000 × 30 000 px pèse peu compressée mais saturerait la mémoire).
        $size = @getimagesize($file['tmp_name']);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            $errors['photo'] = 'Le fichier envoyé n’est pas une image valide.';
            return null;
        }
        if ($size[0] > self::MAX_PIXELS || $size[1] > self::MAX_PIXELS) {
            $errors['photo'] = 'L’image est trop grande (6000 pixels de côté maximum).';
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::EXTENSIONS[$mime];
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true)) {
            $errors['photo'] = 'Le dossier des photos est indisponible.';
            return null;
        }
        $destination = $this->directory . DIRECTORY_SEPARATOR . $filename;

        if (!$this->reencode($file['tmp_name'], $mime, $destination)) {
            // Extension GD absente : on garde le fichier d'origine (déjà vérifié
            // ci-dessus), mais ses métadonnées ne sont pas effacées.
            error_log('Lost&Found : extension GD indisponible, métadonnées EXIF conservées pour ' . $filename);
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $errors['photo'] = 'La photo n’a pas pu être enregistrée.';
                return null;
            }
        }

        return $this->returnPrefix . $filename;
    }

    /** Supprime une photo enregistrée (sans erreur si elle n'existe plus). */
    public function delete(?string $stored): void
    {
        if ($stored === null || $stored === '') {
            return;
        }

        $file = $this->path($stored);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Recrée l'image pixel par pixel avec GD puis l'enregistre.
     *
     * Pourquoi : une photo prise au téléphone contient des métadonnées EXIF,
     * dont souvent les coordonnées GPS exactes de la prise de vue. Publiée telle
     * quelle, la photo d'un objet trouvé « chez moi » révélerait l'adresse de
     * son auteur. Le réencodage ne conserve que les pixels : EXIF, GPS,
     * commentaires et éventuel code caché dans le fichier disparaissent.
     * Bonus : l'image est réduite à STORED_MAX px, les pages sont plus légères.
     *
     * Retourne false si GD n'est pas disponible (rien n'est alors écrit).
     */
    private function reencode(string $source, string $mime, string $destination): bool
    {
        $decoders = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];
        $encoders = ['image/jpeg' => 'imagejpeg', 'image/png' => 'imagepng', 'image/webp' => 'imagewebp'];
        if (!function_exists($decoders[$mime]) || !function_exists($encoders[$mime])) {
            return false;
        }

        $image = @$decoders[$mime]($source);
        if ($image === false) {
            return false;
        }

        // Les téléphones enregistrent souvent la photo « couchée » avec une
        // simple indication d'orientation dans l'EXIF : comme on supprime
        // l'EXIF, on applique cette rotation aux pixels.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($source);
            $angles = [3 => 180, 6 => -90, 8 => 90];
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if (isset($angles[$orientation])) {
                $rotated = imagerotate($image, $angles[$orientation], 0);
                if ($rotated !== false) {
                    $image = $rotated;
                }
            }
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::STORED_MAX / max($width, $height));
        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            if ($resized !== false) {
                $image = $resized;
            }
        }

        if ($mime !== 'image/jpeg') {
            imagesavealpha($image, true); // conserve la transparence PNG/WebP
        }

        return match ($mime) {
            'image/jpeg' => imagejpeg($image, $destination, 85),
            'image/png'  => imagepng($image, $destination, 6),
            'image/webp' => imagewebp($image, $destination, 85),
        };
    }
}
