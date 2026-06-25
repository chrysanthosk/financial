<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'payee_name',
        'expense_category_id',
        'payment_method_id',
        'amount',
        'cheque_no',
        'reason',
        'is_paid_default',
        'auto_create',
        'day_of_month',
        'is_active',
        'last_generated_on',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_paid_default' => 'boolean',
        'auto_create' => 'boolean',
        'is_active' => 'boolean',
        'day_of_month' => 'integer',
        'last_generated_on' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Build (but do not persist) an Expense from this template for $date.
     */
    public function toExpenseAttributes(CarbonInterface $date, ?int $userId = null): array
    {
        return [
            'expense_date' => $date->toDateString(),
            'payee_name' => $this->payee_name,
            'expense_category_id' => $this->expense_category_id,
            'payment_method_id' => $this->payment_method_id,
            'amount' => $this->amount,
            'cheque_no' => $this->cheque_no,
            'reason' => $this->reason,
            'is_paid' => (bool) $this->is_paid_default,
            'created_by' => $userId ?? $this->created_by,
        ];
    }
}
