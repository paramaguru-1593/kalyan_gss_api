<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchemePayment extends Model
{
    protected $fillable = [
        'scheme_enrollment_id',
        'billdesk_reference',
        'amount',
        'installment_no',
        'payment_gateway',
        'bank_reference_no',
        'bank_id',
        'status',
        'payment_date',
        'gateway_response'
    ];

    public function enrollment()
    {
        return $this->belongsTo(SchemeEnrollment::class,'scheme_enrollment_id');
    }
}
