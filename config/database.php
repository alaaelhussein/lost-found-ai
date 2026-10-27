<?php
/**
 * Connexion à la base de données.
 *
 * db() retourne TOUJOURS le même objet PDO pendant une requête HTTP (variable
 * static) : la connexion n'est ouverte qu'une fois, et seulement si une page en
 * a besoin.
 *
 * Utilisation dans un modèle :
 *     $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
 *     $stmt->execute(['id' => $id]);
 *
 * Règle du projet : JAMAIS de variable concaténée dans une requête SQL,
 * toujours prepare() + execute() avec des paramètres.
 */

declare(strict_types=1);

use App\Core\Config;

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $db = Config::get('db');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset']
        );

        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            // Une erreur SQL lève une exception au lieu d'échouer en silence
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // fetch() retourne un tableau associatif ['colonne' => valeur]
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Vraies requêtes préparées côté MySQL (et non simulées par PHP).
            // Conséquence : un même paramètre nommé ne peut pas apparaître
            // deux fois dans une requête (:email ... :email est interdit).
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
