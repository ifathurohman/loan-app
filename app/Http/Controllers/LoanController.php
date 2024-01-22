<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Loan;
use App\Models\Repayment;
use App\Http\Requests\RepayRequest;
use App\Http\Resources\RepaymentResource;

use App\Http\Requests\LoanRequest;
use App\Http\Resources\LoanResource;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

use Illuminate\Support\Facades\Auth;

class LoanController extends Controller
{
    public function submitLoanRequest(LoanRequest $request)
    {

        $date = Carbon::now()->format('Y-m-d');

        $loan = Loan::create([
            'amount' => $request->input('amount'),
            'term' => $request->input('term'),
            'due_date' => Carbon::parse($date),
            'state' => 'PENDING',
        ]);

        $loan->generateRepayments();

        return new LoanResource($loan);
    }

    public function listLoans(Request $request, $loanId = null)
    {
        if ($loanId !== null) {
            $loan = Loan::with('repayments')->findOrFail($loanId);
            return new LoanResource($loan);
        } else {
            $loans = Loan::all();
            return LoanResource::collection($loans);
        }
    }

    public function repayLoan(RepayRequest $request, $loanId)
    {
        $loan = Loan::findOrFail($loanId);

        $request->validated();

        if ($loan->state === 'PAID') {
            return response()->json(['message' => 'Loan is already paid.'], 400);
        }

        // Hardcoded due date for testing purposes
        $hardcodedDueDate = '2024-01-28';  // Replace with your desired date

        $repayment = $loan->repayments()->where('due_date', $hardcodedDueDate)->first();

        if ($repayment) {

            if ($repayment->state === 'PENDING') {

                $repayments = Repayment::find($loanId);

                if ($request->input('repayment_amount') !== $repayments->amount) {
                    return response()->json(['message' => 'amount is not the same ! input this amount:' .$repayments->amount], 400);
                }

                $repayment->update([
                    'state' => 'PAID',
                ]);

                $loan = Loan::findOrFail($loanId);

                $pendingRepaymentsCount = $loan->repayments()
                    ->where('state', '=', 'PENDING')
                    ->count();

                if ($pendingRepaymentsCount === 0) {
                    $loan->update(['state' => 'PAID', 'updated_at' => Carbon::now()->format('Y-m-d H:i:m'),]);
                }

                if ($loan->term === $pendingRepaymentsCount) {
                    $loan->update(['state' => 'PAID']);
                }
            } else {
                return response()->json(['message' => 'This week bill has been paid !'], 400);
            }
        } else {
            return response()->json(['message' => 'No repayment record found for the due date.'], 400);
        }

        return response()->json([
            'loan_id' => $loan->id,
            'repayment' => new RepaymentResource($repayment),
        ]);
    }
}
