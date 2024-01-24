<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Loan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LoanSeedeer extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        $user = User::where('username', 'test')->first();
        Loan::create([
            'amount' => 5000,
            'term' => 12,
            'due_date' => now()->addMonths(1),
            'state' => 'PENDING',
            'user_id' => $user->id,
        ]);
    }
}
