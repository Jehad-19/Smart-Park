<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateEmailRequest;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class UserController extends BaseApiController
{
    /**
     * عرض معلومات المستخدم الشخصية
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();
            return $this->sendSuccess(
                ['user' => new UserResource($user)],
                'تم جلب البيانات بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Profile Error');
        }
    }

    /**
     * تحديث المعلومات الشخصية
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            DB::transaction(function () use ($user, $validated) {
                $user->update([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                ]);
            });

            return $this->sendSuccess(
                ['user' => new UserResource($user->fresh())],
                'تم تحديث البيانات بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Update Profile Error');
        }
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            // التحقق من كلمة المرور الحالية
            if (!Hash::check($validated['current_password'], $user->password)) {
                return $this->sendError('كلمة المرور الحالية غير صحيحة.', [], 400);
            }

            // تحديث كلمة المرور
            DB::transaction(function () use ($user, $validated) {
                $user->password = $validated['new_password']; // <-- لا تستخدم Hash::make هنا
                $user->save(); // <-- استخدم save بدلاً من update
            });

            return $this->sendSuccess([], 'تم تغيير كلمة المرور بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Change Password Error');
        }
    }


    /**
     * طلب تحديث البريد الإلكتروني - إرسال OTP
     */

    public function requestEmailUpdate(UpdateEmailRequest $request)
    {
        try {
            $validated = $request->validated();

            $user = $request->user();
            $cacheKey = 'update_email:' . $user->id;

            $otp = rand(100000, 999999);
            Cache::put($cacheKey, [
                'new_email' => $validated['new_email'],
                'otp' => $otp,
            ], 600); // 10 دقائق

            Mail::to($validated['new_email'])->send(new OtpMail($otp));

            return $this->sendSuccess([], 'تم إرسال رمز التحقق إلى البريد الإلكتروني الجديد.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Request Email Update Error');
        }
    }


    /**
     * التحقق من OTP وتحديث البريد الإلكتروني
     */
    public function verifyEmailUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'otp' => 'required|string|min:6|max:6'
            ], [
                'otp.required' => 'رمز التحقق مطلوب.'
            ]);

            $user = $request->user();
            $cacheKey = 'update_email:' . $user->id;
            $cached = Cache::get($cacheKey);

            if (!$cached) {
                return $this->sendError('لا يوجد طلب تحديث بريد إلكتروني أو انتهت صلاحية الرمز.', [], 404);
            }

            if ($cached['otp'] != $validated['otp']) {
                return $this->sendError('رمز التحقق غير صحيح.', [], 400);
            }

            // تحديث البريد الإلكتروني
            DB::transaction(function () use ($user, $cached) {
                $user->update(['email' => $cached['new_email']]);
            });

            Cache::forget($cacheKey);

            return $this->sendSuccess(
                ['user' => new UserResource($user->fresh())],
                'تم تحديث البريد الإلكتروني بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Verify Email Update Error');
        }
    }

    /**
     * حذف الحساب
     */
    public function deleteAccount(Request $request)
    {
        try {
            $validated = $request->validate([
                'password' => 'required|string'
            ], [
                'password.required' => 'كلمة المرور مطلوبة للتأكيد.'
            ]);

            $user = $request->user();

            // التحقق من كلمة المرور
            if (!Hash::check($validated['password'], $user->password)) {
                return $this->sendError('كلمة المرور غير صحيحة.', [], 400);
            }

            // حذف الحساب والبيانات المرتبطة
            DB::transaction(function () use ($user) {
                // حذف المحفظة والمعاملات سيتم تلقائياً بسبب cascadeOnDelete
                $user->tokens()->delete(); // حذف جميع التوكنات
                $user->delete(); // حذف الحساب
            });

            return $this->sendSuccess([], 'تم حذف الحساب بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Delete Account Error');
        }
    }
}
