<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Categorias/Index');
    }
}
