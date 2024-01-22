<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Loan;

class LoanTest extends TestCase
{

    public function testRepayLoanOnDueDate()
    {
        $loan = Loan::create([
            'amount' => 1000,
            'term' => 3,
            'due_date' => now()->addDays(7), // Assuming due date is 7 days from now
            'state' => 'PENDING',
        ]);

        // Make a POST request to repay-loan endpoint
        $response = $this->post("/repay-loan/{$loan->id}");

        // Assert that the response is successful (status code 200)
        $response->assertStatus(200);

        // Assert that the loan state is now 'PAID'
        $this->assertEquals('PAID', $loan->fresh()->state);

        // Assert that all associated repayments have a state of 'PAID'
        $loan->repayments->each(function ($repayment) {
            $this->assertEquals('PAID', $repayment->fresh()->state);
        });
    }
}
