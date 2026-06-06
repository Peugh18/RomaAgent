<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DeliveryZoneController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ZonasDelivery/Index');
    }
}
