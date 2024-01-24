<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\LoanApprovalHistory;
use App\Http\Requests\LoanRequest;
use App\Http\Requests\RepayRequest;
use App\Http\Resources\LoanResource;
use App\Http\Resources\RepaymentResource;
use Illuminate\Http\JsonResponse;

class LoanController extends Controller
{
    // Method for submitting a new loan request
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

    // Method for listing loans based on user role
    public function listLoans(Request $request, $loanId = null)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return $this->listAdminLoans($loanId);
        } else {
            return $this->listUserLoans($loanId, $user->id);
        }
    }

    // Method for processing loan repayment
    // public function repayLoan(RepayRequest $request, $loanId)
    // {
    //     $loan = Loan::findOrFail($loanId);
    //     $request->validated();

    //     if ($loan->state === 'PAID') {
    //         return response()->json(['message' => 'Loan is already paid.'], 400);
    //     }

    //     $hardcodedDueDate = '2024-01-29'; // Replace with your desired date
    //     $repayment = $loan->repayments()->where('due_date', $hardcodedDueDate)->first();
    //     // $repayment = $loan->repayments()->whereDate('due_date', Carbon::today())->first();


    //     if ($repayment && $repayment->state === 'PENDING') {
    //         return $this->processRepayment($repayment, $request->input('repayment_amount'));
    //     } else {
    //         return response()->json(['message' => 'No valid repayment found for the due date.'], 400);
    //     }
    // }

    public function repayLoan(RepayRequest $request, $loanId)
    {
        $user = Auth::user();
        $loan = Loan::findOrFail($loanId);
        $request->validated();

        // Check if the loan belongs to the authenticated user
        if ($loan->user_id !== $user->id) {
            return response()->json(['message' => 'You are not authorized to repay this loan.'], 403);
        }

        // Check if the loan has been approved by an admin
        if ($loan->state !== 'APPROVED') {
            return response()->json(['message' => 'Loan has not been approved by admin.'], 400);
        }

        // Use Carbon's today method for the due date
        $repayment = $loan->repayments()->whereDate('due_date', Carbon::today())->first();

        if ($repayment && $repayment->state === 'PENDING') {
            return $this->processRepayment($repayment, $request->input('repayment_amount'));
        } else {
            return response()->json(['message' => 'No valid repayment found for the due date.'], 400);
        }
    }

    // Method for approving a loan
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

        $loan->update(['state' => 'APPROVED']);
        $this->createLoanApprovalHistory($loan, $user->id);

        return response()->json([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'message' => 'Loan approved successfully.',
        ]);
    }

    // Method for getting loan details based on user role
    public function getLoanDetails($loanId)
    {
        $user = Auth::user();
        $loan = Loan::findOrFail($loanId);

        if ($user->role === 'admin') {
            return $this->getAdminLoanDetails($loan);
        } elseif ($user->id === $loan->user_id) {
            return $this->getUserLoanDetails($loan);
        } else {
            return response()->json(['message' => 'Unauthorized to view loan details.'], 403);
        }
    }

    // Private method for listing admin loans
    private function listAdminLoans($loanId)
    {
        if ($loanId !== null) {
            $loan = Loan::with('repayments')->findOrFail($loanId);
            return new LoanResource($loan);
        } else {
            $loans = Loan::all();
            return LoanResource::collection($loans);
        }
    }

    // Private method for listing user loans
    private function listUserLoans($loanId, $userId)
    {
        if ($loanId !== null) {
            $loan = Loan::where('id', $loanId)
                ->where('user_id', $userId)
                ->with('repayments')
                ->firstOrFail();
            return new LoanResource($loan);
        } else {
            $userLoans = Loan::where('user_id', $userId)->get();
            return LoanResource::collection($userLoans);
        }
    }

    // Private method for processing loan repayment
    private function processRepayment($repayment, $repaymentAmount)
    {
        $repayments = Repayment::find($repayment->loan_id);

        if ($repaymentAmount !== $repayments->amount) {
            return response()->json(['message' => 'Amount is not the same! Input this amount: ' . $repayments->amount], 400);
        }

        $repayment->update(['state' => 'PAID']);
        $loan = Loan::findOrFail($repayment->loan_id);
        $this->updateLoanState($loan);

        return response()->json([
            'loan_id' => $loan->id,
            'repayment' => new RepaymentResource($repayment),
        ]);
    }

    // Private method for updating loan state
    private function updateLoanState($loan)
    {
        $pendingRepaymentsCount = $loan->repayments()
            ->where('state', '=', 'PENDING')
            ->count();

        if ($pendingRepaymentsCount === 0) {
            $loan->update(['state' => 'PAID', 'updated_at' => Carbon::now()->format('Y-m-d H:i:m')]);
        }

        if ($loan->term === $pendingRepaymentsCount) {
            $loan->update(['state' => 'PAID']);
        }
    }

    // Private method for creating loan approval history
    private function createLoanApprovalHistory($loan, $userId)
    {
        LoanApprovalHistory::create([
            'loan_id' => $loan->id,
            'user_id' => $userId,
            'action' => 'APPROVE',
        ]);
    }

    // Private method for getting admin loan details
    private function getAdminLoanDetails($loan)
    {
        $loanDetails = LoanResource::make($loan)->toArray($loan);
        $loanDetails['approval_history'] = LoanApprovalHistory::where('loan_id', $loan->id)->get();
        $loanDetails['repayments'] = RepaymentResource::collection($loan->repayments);

        return response()->json(['loan_details' => $loanDetails]);
    }

    // Private method for getting user loan details
    private function getUserLoanDetails($loan)
    {
        $loanDetails = LoanResource::make($loan)->toArray($loan);
        $loanDetails['repayments'] = RepaymentResource::collection($loan->repayments);

        return response()->json(['loan_details' => $loanDetails]);
    }
}
