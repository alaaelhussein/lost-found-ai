<?php
/**
 * Router : associe une URL + une méthode HTTP à une méthode de contrôleur.
 *
 * Les routes sont déclarées dans public/index.php :
 *
 *     $router->get('/login', [AuthController::class, 'showLogin']);
 *     $router->post('/login', [AuthController::class, 'login']);
 *     $router->get('/items/{id}', [ItemController::class, 'show']);
 *
 * Paramètres d'URL : un segment {nom} accepte uniquement des chiffres et est
 * transmis à la méthode du contrôleur sous forme d'entier :
 *     /items/42  =>  ItemController->show(42)
 *
 * Réponses d'erreur :
 *   - aucune route ne correspond à l'URL          => 404
 *   - l'URL existe mais pas pour cette méthode    => 405 (ex. GET /logout)
 */

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * Liste des routes. Chaque route :
     * ['method' => 'GET', 'pattern' => '#^/items/([0-9]+)$#', 'handler' => [Classe, 'methode']]
     */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        // On transforme '/items/{id}' en expression régulière '#^/items/([0-9]+)$#'
        $pattern = '#^' . preg_replace('#\{[a-zA-Z_]+\}#', '([0-9]+)', $path) . '$#';

        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * Trouve la route correspondant à la requête et exécute le contrôleur.
     *
     * @param string $method Méthode HTTP ($_SERVER['REQUEST_METHOD'])
     * @param string $uri    URL demandée ($_SERVER['REQUEST_URI']), avec éventuellement ?query
     */
    public function dispatch(string $method, string $uri): void
    {
        // On ignore la query string (?page=2) et le « / » final (/login/ == /login)
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        $pathExists = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $pathExists = true;
            if ($route['method'] !== $method) {
                continue;
            }

            // $matches[0] = l'URL complète, les suivants = les paramètres {id}
            $params = array_map('intval', array_slice($matches, 1));

            [$class, $action] = $route['handler'];
            (new $class())->$action(...$params);
            return;
        }

        if ($pathExists) {
            View::error(405, 'Méthode non autorisée pour cette page.');
            return;
        }

        View::error(404, 'La page demandée est introuvable.');
    }
}
