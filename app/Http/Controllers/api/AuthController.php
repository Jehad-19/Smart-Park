<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends BaseApiController
{
    /**
     * معالجة الأخطاء الاستثنائية وتتبعها
     */
    protected function handleException(\Exception $e, string $logMessage)
    {
        Log::error($logMessage . ': ' . $e->getMessage());
        if (config('app.debug')) {
            $errors = [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            return $this->sendError('حدث خطأ في الخادم.', $errors, 500);
        }
        return $this->sendError('حدث خطأ غير متوقع، يرجى المحاولة مرة أخرى.', [], 500);
    }

    /**
     * الخطوة 1 للتسجيل: إرسال OTP
     */
    public function register(RegisterRequest $request)
    {
        try {
            $validatedData = $request->validated();
            $cacheKey = 'register:' . $validatedData['email'];

            $otp = rand(100000, 999999);
            Cache::put($cacheKey, [
                'data' => $validatedData,
                'otp' => $otp,
            ], 600); // صلاحية 10 دقائق

            Mail::to($validatedData['email'])->send(new OtpMail($otp));

            return $this->sendSuccess([], 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Register Error');
        }
    }

    /**
     * الخطوة 2 للتسجيل: التحقق من OTP وإنشاء الحساب
     */
    public function verifyAccountOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'otp' => 'required|string|min:6|max:6',
            ]);

            $cacheKey = 'register:' . $request->email;
            $cached = Cache::get($cacheKey);

            if (!$cached) {
                return $this->sendError('لا يوجد تسجيل في الانتظار أو انتهت صلاحية الرمز.', [], 404);
            }

            if ($cached['otp'] != $request->otp) {
                return $this->sendError('رمز التحقق غير صحيح.', [], 400);
            }

            $user = null;
            DB::transaction(function () use ($cached, &$user) {
                $data = $cached['data'];
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => bcrypt($data['password']),
                    'fcm_token' => $data['fcm_token'] ?? null,
                    'status' => 'active',
                ]);
                Wallet::create(['user_id' => $user->id]);
            });

            if (!$user) {
                throw new \Exception('فشل في إنشاء المستخدم داخل الـ transaction.');
            }

            Cache::forget($cacheKey);
            $token = $user->createToken('auth_token')->plainTextToken;

            $data = [
                'token' => $token,
                'user' => new UserResource($user),
            ];

            return $this->sendSuccess($data, 'تم إنشاء الحساب وتسجيل الدخول بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Verify Account OTP Error');
        }
    }

    /**
     * تسجيل دخول المستخدم
     */
    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->validated();
            $user = User::where('email', $credentials['email'])->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return $this->sendError('البريد الإلكتروني أو كلمة المرور غير صحيحة.', [], 401);
            }

            if (!$user->status || $user->status !== 'active') {
                return $this->sendError('حسابك معطل. يرجى الاتصال بالدعم الفني.', [], 403);
            }

            $fcm_token = $request->input('fcm_token');
            if ($fcm_token) {
                $user->fcm_token = $fcm_token;
                $user->save();
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            $data = [
                'token' => $token,
                'user' => new UserResource($user),
            ];

            return $this->sendSuccess($data, 'تم تسجيل الدخول بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Login Error');
        }
    }

    /**
     * تسجيل دخول المشرف
     */
    public function adminLogin(Request $request)
    {
        try {
            $request->validate([
                'employee_number' => 'required|string',
                'password' => 'required|string',
            ]);

            $admin = User::where('employee_number', $request->employee_number)->first();

            if (!$admin || !Hash::check($request->password, $admin->password)) {
                return $this->sendError('رقم الموظف أو كلمة المرور غير صحيحة.', [], 401);
            }

            // يمكنك إضافة تحقق للتأكد من أن هذا المستخدم هو مشرف بالفعل
            // if(!$admin->hasRole('admin')) { ... }

            $token = $admin->createToken('admin_auth_token')->plainTextToken;

            return $this->sendSuccess([
                'admin' => new UserResource($admin),
                'token' => $token
            ], 'تم تسجيل دخول المشرف بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Admin Login Error');
        }
    }

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return $this->sendSuccess([], 'تم تسجيل الخروج بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Logout Error');
        }
    }

    /**
     * طلب إعادة تعيين كلمة المرور
     */
    public function forgetPasswordRequest(Request $request)
    {
        try {
            $request->validate(['email' => 'required|email|exists:users,email']);
            $email = $request->email;

            $cacheKey = 'reset:' . $email;
            $otp = rand(100000, 999999);
            Cache::put($cacheKey, ['otp' => $otp], 600);
            Mail::to($email)->send(new OtpMail($otp));

            return $this->sendSuccess([], 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Forget Password Request Error');
        }
    }

    /**
     * التحقق من OTP لإعادة تعيين كلمة المرور
     */
    public function verifyPasswordOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|string'
            ]);

            $cacheKey = 'reset:' . $request->email;
            $cached = Cache::get($cacheKey);

            if (!$cached || $cached['otp'] != $request->otp) {
                return $this->sendError('رمز التحقق غير صحيح أو انتهت صلاحيته.', [], 400);
            }

            $resetToken = Str::random(60);
            $tokenCacheKey = 'reset_token:' . $request->email;
            Cache::put($tokenCacheKey, $resetToken, 600);

            return $this->sendSuccess(['reset_token' => $resetToken], 'تم التحقق بنجاح. يمكنك الآن تعيين كلمة مرور جديدة.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Verify Password OTP Error');
        }
    }

    /**
     * إعادة تعيين كلمة المرور
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'password' => 'required|string|min:8|confirmed',
                'reset_token' => 'required|string'
            ]);

            $tokenCacheKey = 'reset_token:' . $request->email;
            $cachedToken = Cache::get($tokenCacheKey);
            if (!$cachedToken || $cachedToken !== $request->reset_token) {
                return $this->sendError('رمز إعادة التعيين غير صالح أو منتهي الصلاحية.', [], 400);
            }

            $user = User::where('email', $request->email)->first();
            $user->password = Hash::make($request->password);
            $user->save();

            Cache::forget('reset:' . $request->email);
            Cache::forget($tokenCacheKey);

            return $this->sendSuccess([], 'تم تغيير كلمة المرور بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Reset Password Error');
        }
    }

    /**
     * إعادة إرسال رمز التحقق
     */
    public function resendOtp(Request $request)
    {
        try {
            $request->validate(['email' => 'required|email']);
            $email = $request->email;
            $cacheKeyRegister = 'register:' . $email;
            $cacheKeyReset = 'reset:' . $email;

            $keyToSend = null;
            if (Cache::has($cacheKeyRegister)) {
                $keyToSend = $cacheKeyRegister;
                $message = 'تم إعادة إرسال رمز التحقق للتسجيل.';
            } elseif (Cache::has($cacheKeyReset)) {
                $keyToSend = $cacheKeyReset;
                $message = 'تم إعادة إرسال رمز التحقق لإعادة تعيين كلمة المرور.';
            }

            if ($keyToSend) {
                $cached = Cache::get($keyToSend);
                $otp = rand(100000, 999999);
                $cached['otp'] = $otp;
                Cache::put($keyToSend, $cached, 600);
                Mail::to($email)->send(new OtpMail($otp));
                return $this->sendSuccess([], $message);
            }

            return $this->sendError('لا يوجد عملية نشطة لهذا البريد الإلكتروني.', [], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Resend OTP Error');
        }
    }
}
