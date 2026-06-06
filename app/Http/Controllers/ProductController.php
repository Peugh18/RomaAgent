<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Productos/Index');
    }

    public function create(): Response
    {
        return Inertia::render('Productos/Create');
    }

    public function edit(Product $producto): Response
    {
        $producto->load(['category', 'variants']);

        return Inertia::render('Productos/Edit', [
            'product' => $producto,
        ]);
    }
}
