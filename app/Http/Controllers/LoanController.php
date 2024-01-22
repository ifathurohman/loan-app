<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Loan;
use App\Models\Repayment;
use App\Models\LoanApprovalHistory;

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

        $user = Auth::user();
        $date = Carbon::now()->format('Y-m-d');

        $loan = Loan::create([
            'amount' => $request->input('amount'),
            'term' => $request->input('term'),
            'due_date' => Carbon::parse($date),
            'state' => 'PENDING',
            'user_id' => $user->id,
        ]);

        $loan->generateRepayments();

        return new LoanResource($loan);
    }

    public function listLoans(Request $request, $loanId = null)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            // If admin, return all loans or a specific loan based on the provided $loanId
            if ($loanId !== null) {
                $loan = Loan::with('repayments')->findOrFail($loanId);
                return new LoanResource($loan);
            } else {
                $loans = Loan::all();
                return LoanResource::collection($loans);
            }
        } else {
            // If user, return only their own loans or a specific loan based on the provided $loanId
            if ($loanId !== null) {
                $loan = Loan::where('id', $loanId)
                    ->where('user_id', $user->id)
                    ->with('repayments')
                    ->firstOrFail();
                return new LoanResource($loan);
            } else {
                // Fetch loans associated with the authenticated user
                $userLoans = Loan::where('user_id', $user->id)->get();
                return LoanResource::collection($userLoans);
            }
        }
    }

    // public function repayLoan(RepayRequest $request, $loanId)
    // {
    //     $loan = Loan::findOrFail($loanId);

    //     $request->validated();

    //     if ($loan->state === 'PAID') {
    //         return response()->json(['message' => 'Loan is already paid.'], 400);
    //     }

    //     if ($loan->state === 'PENDING') {
    //         return response()->json(['message' => 'Your loan application has not been approved by admin !'], 400);
    //     }

    //     // Hardcoded due date for testing purposes
    //     $hardcodedDueDate = '2024-01-28';  // Replace with your desired date

    //     $repayment = $loan->repayments()->where('due_date', $hardcodedDueDate)->first();

    //     if ($repayment) {

    //         if ($repayment->state === 'PENDING') {

    //             $repayments = Repayment::find($loanId);

    //             if ($request->input('repayment_amount') !== $repayments->amount) {
    //                 return response()->json(['message' => 'amount is not the same ! input this amount:' . $repayments->amount], 400);
    //             }

    //             $repayment->update([
    //                 'state' => 'PAID',
    //             ]);

    //             $loan = Loan::findOrFail($loanId);

    //             $pendingRepaymentsCount = $loan->repayments()
    //                 ->where('state', '=', 'PENDING')
    //                 ->count();

    //             if ($pendingRepaymentsCount === 0) {
    //                 $loan->update(['state' => 'PAID', 'updated_at' => Carbon::now()->format('Y-m-d H:i:m'),]);
    //             }

    //             if ($loan->term === $pendingRepaymentsCount) {
    //                 $loan->update(['state' => 'PAID']);
    //             }
    //         } else {
    //             return response()->json(['message' => 'This week bill has been paid !'], 400);
    //         }
    //     } else {
    //         return response()->json(['message' => 'No repayment record found for the due date.'], 400);
    //     }

    //     return response()->json([
    //         'loan_id' => $loan->id,
    //         'repayment' => new RepaymentResource($repayment),
    //     ]);
    // }

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
                    return response()->json(['message' => 'amount is not the same ! input this amount:' . $repayments->amount], 400);
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


    public function approveLoan($loanId)
    {
        $user = Auth::user();
        $loan = Loan::findOrFail($loanId);

        if ($user->role === 'user') {
            return response()->json(['message' => 'Unauthorized to approve loans.'], 403);
        }

        if ($loan->state === 'APPROVED') {
            return response()->json(['message' => 'Loan is already approved.'], 400);
        }

        $loan = loan::where('id', $loanId)->first();

        // $loan->update(['state' => 'APPROVED', 'user_id' => $user->id, 'updated_at' => Carbon::now()->format('Y-m-d H:i:m')]);

        $loan->update(['state' => 'APPROVED']);

        // Create an entry in the loan_approval_histories table
        $loan = LoanApprovalHistory::create([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'action' => 'APPROVE', // You can customize this based on your needs
        ]);

        return response()->json([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'message' => 'Loan approved successfully.',
        ]);
    }

    public function getLoanDetails($loanId)
    {
        $user = Auth::user();
        $loan = Loan::findOrFail($loanId);

        // Check if the authenticated user has the 'admin' role
        if ($user->role === 'admin') {
            // For admin, return loan details with approval history and repayments
            $loanDetails = LoanResource::make($loan)->toArray($loan);
            $loanDetails['approval_history'] = LoanApprovalHistory::where('loan_id', $loan->id)->get();
            $loanDetails['repayments'] = RepaymentResource::collection($loan->repayments);
            return response()->json(['loan_details' => $loanDetails]);
        } elseif ($user->id === $loan->user_id) {
            // For user, return loan details with repayments
            $loanDetails = LoanResource::make($loan)->toArray($loan);
            $loanDetails['repayments'] = RepaymentResource::collection($loan->repayments);
            return response()->json(['loan_details' => $loanDetails]);
        } else {
            return response()->json(['message' => 'Unauthorized to view loan details.'], 403);
        }
    }
}
