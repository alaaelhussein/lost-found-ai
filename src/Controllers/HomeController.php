<?php
/**
 * HomeController : page d'accueil.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Item;

final class HomeController
{
    /** GET / */
    public function index(): void
    {
        View::render('home', [
            'title' => 'Accueil',
            'items' => (new Item())->search([], 6),
        ]);
    }
}
