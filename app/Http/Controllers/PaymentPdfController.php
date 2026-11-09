<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\Permissions;
use App\Support\Reports;

/**
 * Descarga del recibo de un pago en PDF.
 * Requiere sesión, que el pago sea de su centro y "Ver todos los pagos" o "Ver mis pagos" (si es suyo).
 */
class PaymentPdfController extends Controller
{
    public function __invoke(Payment $payment)
    {
        $user = auth()->user();

        abort_unless($user, 403);
        abort_unless($payment->center_id === $user->center_id, 404);

        // Todos los pagos del centro, o solo los suyos con "Ver mis pagos"
        $canSee = $user->hasPermission(Permissions::PAYMENTS_VIEW)
            || ($user->hasPermission(Permissions::PAYMENTS_VIEW_OWN) && $payment->user_id === $user->id);

        abort_unless($canSee, 403);

        return Reports::paymentReceiptPdf($payment);
    }
}
