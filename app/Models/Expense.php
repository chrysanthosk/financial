<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_date',
        'payee_name',
        'expense_category_id',
        'payment_method_id',
        'amount',
        'cheque_no',
        'reason',
        'is_paid',     // ✅ NEW
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'is_paid' => 'boolean', // ✅ NEW
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
