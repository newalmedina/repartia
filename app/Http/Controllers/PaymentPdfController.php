<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\Permissions;
use App\Support\Reports;

/**
 * Descarga del recibo de un pago en PDF.
 * Requiere sesión iniciada, permiso "Ver pagos" y que el pago sea de su centro.
 */
class PaymentPdfController extends Controller
{
    public function __invoke(Payment $payment)
    {
        $user = auth()->user();

        abort_unless($user, 403);
        abort_unless($user->hasPermission(Permissions::PAYMENTS_VIEW), 403);
        abort_unless($payment->center_id === $user->center_id, 404);

        return Reports::paymentReceiptPdf($payment);
    }
}
