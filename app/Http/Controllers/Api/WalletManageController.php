<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentWalletRequest;
use App\Models\DealerWalletRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use function Illuminate\Foundation\Configuration\respond;

class WalletManageController extends Controller
{
    public function agentWalletRecharge(Request $request)
    {
        $dealerId = $request->user_id;
        $rechargeAmount = (float)$request->amount;

        if ($rechargeAmount <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Wallet amount should be greater than 0'], 400);
        }
        $user = Auth::user();

        if ($user->is_agent == 1) {
            if ($dealerId) {
                $dealer = User::where('id', $dealerId)->first();

                if ($dealer) {
                    if ($dealer->created_by === $user->id) {
                        $dealer->deposit($rechargeAmount);
                        return response()->json(['status' => 'success', 'message' => 'Wallet credited for user ' . $dealer->name, 'data' => $dealer], 200);
                    } else {
                        return response()->json(['status' => 'error', 'message' => 'You have no access to recharge this user'], 400);
                    }
                } else {
                    return response()->json(['status' => 'error', 'message' => 'User not found'], 400);
                }
            } else {
                return response()->json(['status' => 'error', 'message' => 'User ID required'], 400);
            }
        } else {
            return response()->json(['status' => 'error', 'message' => 'You have no access to recharge dealer wallet'], 400);
        }
    }

    public function dealerWalletRequest(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
        }

        $requestAmount = (float)$request->amount;
        if ($requestAmount <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Request amount must be greater than 0'], 400);
        }

        $requestFromId = $user->id;
        $requestToId = $user->created_by;

        if (!$requestToId) {
            return response()->json(['status' => 'error', 'message' => 'No creator found for this user'], 400);
        }

        $requestUser = User::find($requestToId);
        if (!$requestUser) {
            return response()->json(['status' => 'error', 'message' => 'Requested agent not found'], 400);
        }

        $dealerReq = DealerWalletRequest::create([
            'request_from' => $requestFromId,
            'request_to' => $requestToId,
            'wallet_for' => $requestFromId,
            'amount' => $requestAmount,
            'status' => 0,
        ]);

        if ($dealerReq) {
            return response()->json(['status' => 'success', 'message' => 'Request placed successfully'], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Failed to place request'], 500);
        }
    }

    public function dealerRequestStatusUpdate(Request $request)
    {
        $type = $request->type;
        $requestId = $request->request_id;
        $user = Auth::user();

        if (!$type || !$requestId) {
            return response()->json(['status' => 'error', 'message' => 'Invalid request parameters'], 400);
        }

        $dealerRequest = DealerWalletRequest::find($requestId);

        if (!$dealerRequest) {
            return response()->json(['status' => 'error', 'message' => 'Dealer request not found'], 404);
        }

        if ($user->is_agent == 1) {
            if ($type === 'accept') {
                $dealerRequest->status = 1;
                $dealerRequest->approved_at = now();
                $dealerRequest->save();

                $adminUsers = User::where('is_super_admin', 1)->get();
                foreach ($adminUsers as $adminUser) {
                    AgentWalletRequest::create([
                        'request_from' => $user->id,
                        'request_to' => $adminUser->id,
                        'wallet_for' => $dealerRequest->request_from,
                        'dealer_request_id' => $requestId,
                        'amount' => $dealerRequest->amount,
                        'status' => 0,
                    ]);
                }

                return response()->json(['status' => 'success', 'message' => 'Request accepted. Admin will receive this request.'], 200);

            } elseif ($type === 'reject') {
                $dealerRequest->status = 2;
                $dealerRequest->save();

                return response()->json(['status' => 'success', 'message' => 'Request rejected.'], 200);

            } else {
                return response()->json(['status' => 'error', 'message' => 'Invalid request type'], 400);
            }
        } else {
            return response()->json(['status' => 'error', 'message' => 'You have no permission to do this operation'], 400);
        }

    }

    public function agentRequestStatusUpdate(Request $request)
    {
        $type = $request->type;
        $requestId = $request->request_id;

        if (!$type || !$requestId) {
            return response()->json(['status' => 'error', 'message' => 'Invalid request parameters'], 400);
        }

        $agentRequest = AgentWalletRequest::find($requestId);

        if (!$agentRequest) {
            return response()->json(['status' => 'error', 'message' => 'Agent wallet request not found'], 404);
        }

        if ($agentRequest->status != 0 && $agentRequest->status != 2) {
            return response()->json(['status' => 'error', 'message' => 'Request already approved or rejected'], 400);
        }

        if ($type === 'accept') {
            $dealer = User::find($agentRequest->wallet_for);
            if ($dealer) {
                $dealer->deposit($agentRequest->amount);
                $agentRequest->wallet_status = 1;
            }

            $agentRequest->status = 1;
            $agentRequest->approved_at = now();
            $agentRequest->save();

            if ($agentRequest->request_from == $agentRequest->wallet_for) {
                $this->updateAllAgentRequests($agentRequest->dealer_request_id);
                return response()->json(['status' => 'success', 'message' => 'Request accepted. Dealer wallet updated.'], 200);
            } elseif ($agentRequest->getDealerReq) {
                $dealerRequest = $agentRequest->getDealerReq;
                $this->updateAllAgentRequests($agentRequest->dealer_request_id);
                if ($this->areAllAgentRequestsApproved($agentRequest->dealer_request_id)) {
                    $dealerRequest->status = 1;
                    $dealerRequest->wallet_status = 1;
                    $dealerRequest->approved_at = now();
                    $dealerRequest->save();
                }
                return response()->json(['status' => 'success', 'message' => 'Request accepted. Dealer wallet updated.'], 200);
            }
        } elseif ($type === 'reject') {
            $agentRequest->status = 2;
            $agentRequest->wallet_status = 2;
            $agentRequest->save();

            if ($agentRequest->getDealerReq) {
                $dealerRequest = $agentRequest->getDealerReq;
                $dealerRequest->status = 2;
                $dealerRequest->wallet_status = 2;
                $dealerRequest->save();
            }

            return response()->json(['status' => 'success', 'message' => 'Request rejected.'], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Invalid request type'], 400);
        }
    }

    private function updateAllAgentRequests($dealerRequestId)
    {
        $allAgentReqs = AgentWalletRequest::where('dealer_request_id', $dealerRequestId)->get();

        foreach ($allAgentReqs as $agentReq) {
            if ($agentReq->status == 0) {
                $agentReq->status = 1;
                $agentReq->wallet_status = 1;
                $agentReq->approved_at = now();
                $agentReq->save();
            }
        }
    }

    private function areAllAgentRequestsApproved($dealerRequestId)
    {
        $allAgentReqs = AgentWalletRequest::where('dealer_request_id', $dealerRequestId)->get();

        return $allAgentReqs->every(function ($req) {
            return $req->status == 1;
        });
    }

    public function adminWalletRecharge(Request $request)
    {
        $agentId = $request->user_id;
        $rechargeAmount = (float)$request->amount;

        if ($rechargeAmount <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Wallet amount should be greater than 0'], 400);
        }
        $user = Auth::user();
        if ($agentId) {
            $agent = User::where('id', $agentId)->first();
            if ($agent) {
                if ($agent->created_by === $user->id) {
                    $agent->deposit($rechargeAmount);
                    return response()->json(['status' => 'success', 'message' => 'Wallet credited for user ' . $agent->name, 'data' => $agent], 200);
                } else {
                    return response()->json(['status' => 'error', 'message' => 'You have no access to recharge this user'], 400);
                }
            } else {
                return response()->json(['status' => 'error', 'message' => 'User not found'], 400);
            }
        } else {
            return response()->json(['status' => 'error', 'message' => 'User ID required'], 400);
        }
    }

    public function agentWalletRequestAdmin(Request $request)
    {

        $user = Auth::user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
        }

        $requestAmount = (float)$request->amount;
        if ($requestAmount <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Request amount must be greater than 0'], 400);
        }

        $requestFromId = $user->id;

        $adminUsers = User::where('is_super_admin', 1)->get();


        foreach ($adminUsers as $adminUser){
            $dealerReq = AgentWalletRequest::create([
                'request_from' => $requestFromId,
                'request_to' => $adminUser->id,
                'wallet_for' => $requestFromId,
                'amount' => $requestAmount,
                'status' => 0,
            ]);
        }

        if ($dealerReq) {
            return response()->json(['status' => 'success', 'message' => 'Request placed successfully'], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Failed to place request'], 500);
        }
    }

    public function walletRequestList()
    {
        $user = Auth::user();
        if($user->is_super_admin == 1){
            $list = AgentWalletRequest::with(['requestUser', 'forUser'])->where('request_to', $user->id)
            ->where('status', 0)->orderBy('id', 'DESC')->get();
            return response()->json(['status' => 'success', 'message' => 'data fetched success', 'data' => $list], 200);
        }else if ($user->is_agent == 1){
            $list = DealerWalletRequest::with(['requestUser'])->where('request_to', $user->id)
            ->where('status', 0)->orderBy('id', 'DESC')->get();
            return response()->json(['status' => 'success', 'message' => 'data fetched success', 'data' => $list], 200);
        }else{
            return response()->json(['status'=>'error', 'message' => 'You have no access'], 400);
        }
    }

    public function userWalletRequest(){
        $user = Auth::user();
        if($user){
            if($user->is_agent == 1){
                $agentWalletReq = AgentWalletRequest::where('wallet_for', $user->id)->get();
                return response()->json(['success' => 'Agent wallet request fetched successfully', 'data' => $agentWalletReq], 200);
            }else{
                $userWalletReq = DealerWalletRequest::where('wallet_for', $user->id)->get();
                return response()->json(['success' => 'Dealer wallet request fetched successfully', 'data' => $userWalletReq], 200);
            }

        }else{
            return response()->json(['error' => 'Game not found'], 400);
        }
    }

    public function editWalletRequest(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
        }

        $walletRequest = $user->is_agent
            ? AgentWalletRequest::where('id', $id)->where('wallet_for', $user->id)->first()
            : DealerWalletRequest::where('id', $id)->where('wallet_for', $user->id)->first();

        if (!$walletRequest) {
            return response()->json(['status' => 'error', 'message' => 'Wallet request not found'], 404);
        }

        $requestAmount = (float)$request->amount;
        if ($requestAmount <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Request amount must be greater than 0'], 400);
        }

        $walletRequest->amount = $requestAmount;
        $walletRequest->save();

        return response()->json(['status' => 'success', 'message' => 'Wallet request updated successfully', 'data' => $walletRequest], 200);
    }

    public function deleteWalletRequest($id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
        }

        $walletRequest = $user->is_agent
            ? AgentWalletRequest::where('id', $id)->where('wallet_for', $user->id)->first()
            : DealerWalletRequest::where('id', $id)->where('wallet_for', $user->id)->first();

        if (!$walletRequest) {
            return response()->json(['status' => 'error', 'message' => 'Wallet request not found'], 404);
        }

        $walletRequest->delete();

        return response()->json(['status' => 'success', 'message' => 'Wallet request deleted successfully'], 200);
    }


}
