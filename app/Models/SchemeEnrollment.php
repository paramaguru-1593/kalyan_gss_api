<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single scheme enrollment for a customer (getSchemesByMobileNumber response).
 * Belongs to one customer; enrollment_id is unique from third-party API.
 */
class SchemeEnrollment extends Model
{
    use HasFactory;

    protected $table = 'scheme_enrollments';

    protected $fillable = [
        'customer_id',
        'scheme_id',
        'scheme_name',
        'enrollment_id',
        'enrollment_date',
        'maturity_date',
        'installment_amount',
        'paid_amount',
        'pending_amount',
        'status',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'maturity_date' => 'date',
        'installment_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
    ];

    /**
     * Customer that owns this enrollment.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
