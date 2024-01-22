<?php

namespace App\Http\Controllers;

use App\Models\LoanApprovalHistory;
use App\Http\Resources\LoanApprovalHistoryResource;

class LoanApprovalHistoryController extends Controller
{
    public function index($loanId)
    {
        $loanApprovalHistory = LoanApprovalHistory::where('loan_id', $loanId)->get();

        return LoanApprovalHistoryResource::collection($loanApprovalHistory);
    }
}
