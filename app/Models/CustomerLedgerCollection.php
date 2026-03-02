<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single payment/collection record from getCustomerLedgerReport (Collections array).
 */
class CustomerLedgerCollection extends Model
{
    use HasFactory;

    protected $table = 'customer_ledger_collections';

    protected $fillable = [
        'customer_scheme_detail_id',
        'reference_no',
        'mop',
        'remarks',
        'collection_date',
        'amount',
        'issued_date',
        'cheque_number',
        'emi_month',
        'payment_status',
        'gold_rate',
        'gold_weight',
    ];

    protected $casts = [
        'collection_date' => 'date',
        'issued_date' => 'date',
        'amount' => 'decimal:2',
        'gold_rate' => 'decimal:4',
        'gold_weight' => 'decimal:4',
    ];

    /**
     * Parent scheme detail (ledger report).
     */
    public function customerSchemeDetail(): BelongsTo
    {
        return $this->belongsTo(CustomerSchemeDetail::class);
    }
}
