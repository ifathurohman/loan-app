<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Loan extends Model
{
    protected $primaryKey = "id";
    protected $keyType = "int";
    protected $table = "loans";
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable =  [
        'amount',
        'term',
        'due_date',
        'state',
        'scheduled_repayments',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Loan::class, "user_id", "id");
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class);
    }

    public function generateRepayments()
    {
        $repayments = [];
        $repaymentAmount = $this->amount / $this->term;
        $dueDate = $this->due_date->copy();

        for ($i = 1; $i <= $this->term; $i++) {
            $repayments[] = [
                'loan_id' => $this->id,
                'amount' => $repaymentAmount,
                'due_date' => $dueDate->copy()->addWeeks($i),
                'state' => 'PENDING',
                'created_at' => Carbon::now()->format('Y-m-d H:i:m'),
                // 'updated_at' => now(),
            ];
        }

        Repayment::insert($repayments);
    }
}
