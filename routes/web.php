<?php

use App\Http\Controllers\PaymentPdfController;
use Illuminate\Support\Facades\Route;

// Todo el panel vive en /admin
Route::redirect('/', '/admin');

// Recibo de un pago en PDF (comprueba sesión, permiso y centro)
Route::get('/pagos/{payment}/pdf', PaymentPdfController::class)->name('payments.pdf');
