<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCustomerRequest;
use App\Http\Requests\RegisterTankerRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\SettingsService;
use App\Support\Mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private NotificationService $notifier) {}

    public function registerCustomer(RegisterCustomerRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->only('name', 'mobile', 'email', 'password') + ['role' => 'customer', 'status' => 'active']);
            $user->customerProfile()->create($request->only('address', 'area', 'landmark'));
            return $user;
        });
        $this->notifier->notifyAdmins('New customer registered', "{$user->name} ({$user->mobile})", 'customer.registered', ['user_id' => $user->id]);
        return $this->created($this->session($user), 'Registration successful');
    }

    public function registerTanker(RegisterTankerRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->only('name', 'mobile', 'email', 'password') + ['role' => 'tanker', 'status' => 'active']);
            $profile = $user->tankerProfile()->create([
                'owner_name' => $request->name, 'business_name' => $request->business_name,
                'alternate_mobile' => $request->alternate_mobile, 'address' => $request->address,
                'base_latitude' => $request->base_latitude, 'base_longitude' => $request->base_longitude,
                'verification_status' => 'pending_review',
            ]);
            // Single-vehicle operator: owner is also the driver.
            if ($request->boolean('is_driver', true)) {
                $profile->drivers()->create(['name' => $request->name, 'mobile' => $request->mobile, 'is_owner' => true, 'user_id' => $user->id]);
            }
            return $user;
        });
        $this->notifier->notifyAdmins('New tanker registration', "{$user->name} ({$user->mobile}) is awaiting approval.", 'tanker.registered', ['user_id' => $user->id]);
        return $this->created($this->session($user), 'Registration submitted. Add your vehicle details; admin will review your profile.');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string|max:150', 'password' => 'required|string', 'role' => 'nullable|in:admin,customer,tanker']);
        $login = trim($data['login']);
        $query = str_contains($login, '@') ? User::where('email', strtolower($login)) : User::where('mobile', Mobile::normalize($login));
        $user = $query->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => ['Invalid mobile/email or password.']]);
        }
        if (! empty($data['role']) && $data['role'] !== $user->role && ! ($data['role'] === 'customer' && $user->isAdmin())) {
            throw ValidationException::withMessages(['login' => ['This account is registered as ' . $user->role . '. Please use the ' . $user->role . ' login.']]);
        }
        if ($user->status !== 'active') {
            throw new BusinessRuleException('Your account is ' . str_replace('_', ' ', $user->status) . ($user->status_reason ? ': ' . $user->status_reason : '') . '. Contact support.', 403);
        }
        $user->update(['last_login_at' => now()]);
        if ($user->isAdmin()) AuditLogger::log('auth.admin_login', $user, null, null, $user->id);
        return $this->ok($this->session($user), 'Logged in');
    }

    public function me(Request $request)
    {
        return $this->ok($this->userPayload($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return $this->ok(null, 'Logged out');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password is incorrect.']]);
        }
        $user->update(['password' => $data['password']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        return $this->ok(null, 'Password changed');
    }

    /** Always returns the same message so the endpoint does not reveal whether a number is registered. */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['mobile' => 'required|string|max:20']);
        $mobile = Mobile::normalize($data['mobile']);
        $user = User::where('mobile', $mobile)->where('status', 'active')->first();
        if ($user) {
            $otp = (string) random_int(100000, 999999);
            DB::table('password_reset_otps')->where('mobile', $mobile)->whereNull('used_at')->update(['used_at' => now()]);
            DB::table('password_reset_otps')->insert(['mobile' => $mobile, 'otp_hash' => Hash::make($otp), 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);
            $this->notifier->sendSms($mobile, "Your password reset OTP is {$otp}. Valid for 10 minutes.");
        }
        return $this->ok(null, 'If this mobile number is registered, an OTP has been sent.');
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate(['mobile' => 'required|string', 'otp' => 'required|digits:6', 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $mobile = Mobile::normalize($data['mobile']);
        $row = DB::table('password_reset_otps')->where('mobile', $mobile)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $row || $row->attempts >= 5 || ! Hash::check($data['otp'], $row->otp_hash)) {
            if ($row) DB::table('password_reset_otps')->where('id', $row->id)->increment('attempts');
            throw ValidationException::withMessages(['otp' => ['Invalid or expired OTP.']]);
        }
        $user = User::where('mobile', $mobile)->firstOrFail();
        DB::transaction(function () use ($row, $user, $data) {
            DB::table('password_reset_otps')->where('id', $row->id)->update(['used_at' => now()]);
            $user->update(['password' => $data['password']]);
            $user->tokens()->delete();
        });
        return $this->ok(null, 'Password reset. Please log in with your new password.');
    }

    private function session(User $user): array
    {
        $user->tokens()->where('created_at', '<', now()->subDays(30))->delete();
        return ['token' => $user->createToken('web-' . $user->role)->plainTextToken, 'user' => $this->userPayload($user)];
    }

    private function userPayload(User $user): array
    {
        $data = $user->only('id', 'name', 'mobile', 'email', 'role', 'status');
        if ($user->isTanker()) {
            $profile = $user->operatingTankerProfile();
            $data['tanker'] = $profile ? ['id' => $profile->id, 'verification_status' => $profile->verification_status, 'review_notes' => $profile->review_notes, 'is_owner' => $profile->user_id === $user->id] : null;
        }
        $data['unread_notifications'] = $user->appNotifications()->whereNull('read_at')->count();
        return $data;
    }
}
