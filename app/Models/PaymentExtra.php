<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Importe extra de un pago (con su comentario) */
class PaymentExtra extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
