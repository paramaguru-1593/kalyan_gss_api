<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scheme catalog: savings/enrollment schemes with installment rules,
 * terms and benefits. Referenced by store-based list API and enrollments.
 *
 * @see .cursor/plans/schemes_table_db_design_4acf0723.plan.md
 */
class Scheme extends Model
{
    use HasFactory;

    protected $table = 'schemes';

    protected $guarded = [];

    protected $casts = [
        'weight_allocation' => 'boolean',
        'min_installment_amount' => 'decimal:2',
        'max_installment_amount' => 'decimal:2',
        'benefits_content' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Store offering this scheme.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(KjStore::class, 'store_id');
    }
}
