<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer scheme details from getCustomerLedgerReport (personalDetails block).
 * One row per enrollment; keyed by enrollment_no.
 */
class CustomerSchemeDetail extends Model
{
    use HasFactory;

    protected $table = 'customer_scheme_details';

    protected $fillable = [
        'enrollment_no',
        'customer_id',
        'scheme_enrollment_id',
        'customer_id_external',
        'scheme_id',
        'scheme_name',
        'scheme_status',
        'join_date',
        'maturity_date',
        'closure_date',
        'no_of_installments',
        'emi',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'fee_amount',
        'totalamount',
        'first_name',
        'last_name',
        'mobile_number',
        'email_address',
        'address1',
        'address2',
        'address3',
        'pincode',
        'state',
        'branch',
        'my_kalyan_name',
        'material_type',
        'user_first_name',
        'user_last_name',
        'username',
        'instore_user_id',
        'instore_user_name',
        'id_proof_name',
        'id_proof_number',
        'id_proof_type',
        'beneficiary',
        'date_of_birth',
        'gender',
        'nominee_first_name',
        'nominee_last_name',
        'nominee_relationship',
        'nominee_mobile_number',
        'nominee_address',
        'nominee_email_address',
        'scheme_efficient_type',
        'reason_for_in_efficient',
        'sioncc',
        'transaction_id',
        'emandate',
        'debit_date',
    ];

    protected $casts = [
        'join_date' => 'date',
        'maturity_date' => 'date',
        'closure_date' => 'date',
        'date_of_birth' => 'date',
        'emi' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'totalamount' => 'decimal:2',
        'sioncc' => 'boolean',
        'emandate' => 'boolean',
    ];

    /**
     * Customer (local) when linked.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scheme enrollment (local) when linked.
     */
    public function schemeEnrollment(): BelongsTo
    {
        return $this->belongsTo(SchemeEnrollment::class);
    }

    /**
     * Payment collections for this ledger/scheme detail.
     */
    public function collections(): HasMany
    {
        return $this->hasMany(CustomerLedgerCollection::class, 'customer_scheme_detail_id');
    }
}
