<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\DepositRequest;
use App\Http\Resources\WalletResource;
use App\Http\Resources\TransactionResource;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends BaseApiController
{
    /**
     * عرض رصيد المحفظة
     */
    public function balance(Request $request)
    {
        try {
            $user = $request->user();
            $wallet = $user->wallet;

            if (!$wallet) {
                return $this->sendError('المحفظة غير موجودة.', [], 404);
            }

            return $this->sendSuccess(
                ['wallet' => new WalletResource($wallet)],
                'تم جلب المحفظة بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Get Balance Error');
        }
    }

    /**
     * شحن المحفظة (محاكاة سداد)
     */
    public function deposit(DepositRequest $request)
    {
        try {
            $user = $request->user();
            $wallet = $user->wallet;

            if (!$wallet) {
                return $this->sendError('المحفظة غير موجودة.', [], 404);
            }

            $validated = $request->validated();
            $amount = $validated['amount'];

            DB::transaction(function () use ($wallet, $amount) {
                $balanceBefore = $wallet->balance;
                $wallet->balance += $amount;
                $wallet->save();

                // إنشاء سجل معاملة
                Transaction::create([
                    'wallet_id' => $wallet->id,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->balance,
                    'description' => 'شحن المحفظة عبر سداد',
                    'reference_id' => null,
                    'reference_type' => 'sadad',
                ]);
            });

            return $this->sendSuccess(
                ['wallet' => new WalletResource($wallet->fresh())],
                'تم شحن المحفظة بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Deposit Error');
        }
    }

    /**
     * سجل المعاملات
     */
    public function transactions(Request $request)
    {
        try {
            $user = $request->user();
            $wallet = $user->wallet;

            if (!$wallet) {
                return $this->sendError('المحفظة غير موجودة.', [], 404);
            }

            $perPage = $request->input('per_page', 15);
            $type = $request->input('type'); // deposit, withdrawal, refund

            $query = $wallet->transactions()->latest();

            if ($type) {
                $query->where('type', $type);
            }

            $transactions = $query->paginate($perPage);

            return $this->sendSuccess([
                'transactions' => TransactionResource::collection($transactions),
                'pagination' => [
                    'total' => $transactions->total(),
                    'per_page' => $transactions->perPage(),
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                ]
            ], 'تم جلب سجل المعاملات بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Get Transactions Error');
        }
    }

    /**
     * تفاصيل معاملة محددة
     */
    public function transactionDetails(Request $request, $id)
    {
        try {
            $user = $request->user();
            $wallet = $user->wallet;

            if (!$wallet) {
                return $this->sendError('المحفظة غير موجودة.', [], 404);
            }

            $transaction = $wallet->transactions()->find($id);

            if (!$transaction) {
                return $this->sendError('المعاملة غير موجودة.', [], 404);
            }

            return $this->sendSuccess(
                ['transaction' => new TransactionResource($transaction)],
                'تم جلب تفاصيل المعاملة بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Get Transaction Details Error');
        }
    }
}
