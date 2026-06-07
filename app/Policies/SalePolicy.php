<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * Permitir que cualquier usuario autenticado vea ventas.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Permitir que cualquier usuario autenticado vea una venta específica.
     */
    public function view(User $user, Sale $sale): bool
    {
        return true;
    }

    /**
     * Confirmar pago de una venta.
     * Autorización: cualquier usuario autenticado puede intentar confirmar.
     * La validación de estado se hace en el controller.
     */
    public function confirmPayment(User $user, Sale $sale): bool
    {
        return true;
    }

    /**
     * Marcar venta como enviada.
     * Autorización: cualquier usuario autenticado puede intentar marcar como enviado.
     * La validación de estado se hace en el controller.
     */
    public function markShipped(User $user, Sale $sale): bool
    {
        return true;
    }

    public function markDelivered(User $user, Sale $sale): bool
    {
        return true;
    }

    /**
     * Permitir que cualquier usuario autenticado cree ventas (vía IA o manual).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Actualizar venta.
     */
    public function update(User $user, Sale $sale): bool
    {
        return true;
    }

    /**
     * Eliminar venta.
     */
    public function delete(User $user, Sale $sale): bool
    {
        return true;
    }
}
