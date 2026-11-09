<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class SettingController extends Controller
{
    public function index()
    {
        return $this->ok(Setting::orderBy('id')->get()->map(fn ($s) => $s->only('key', 'label', 'type', 'is_public') + ['value' => $s->castValue()]));
    }

    public function update(Request $request)
    {
        $data = $request->validate(['settings' => 'required|array']);
        $changed = [];
        foreach ($data['settings'] as $key => $value) {
            $s = Setting::where('key', $key)->first();
            if (! $s) continue;
            $value = match ($s->type) {
                'json' => json_encode(is_string($value) ? json_decode($value, true) : $value),
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                'int' => (string) (int) $value,
                'decimal' => (string) (float) $value,
                default => (string) $value,
            };
            if ($s->value !== $value) {
                $changed[$key] = ['from' => $s->value, 'to' => $value];
                $s->update(['value' => $value]);
            }
        }
        Setting::flush();
        if ($changed) AuditLogger::log('settings.updated', null, null, $changed);
        return $this->index();
    }

    public function admins()
    {
        return $this->ok(User::where('role', 'admin')->get(['id', 'name', 'email', 'mobile', 'status', 'last_login_at', 'created_at']));
    }

    public function storeAdmin(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'password' => ['required', Password::min(10)->letters()->numbers()]]);
        $u = User::create($data + ['role' => 'admin', 'status' => 'active']);
        AuditLogger::log('admin.created', $u, null, $u->only('name', 'email'));
        return $this->created($u, 'Admin user created');
    }

    public function updateAdmin(Request $request, User $admin)
    {
        abort_unless($admin->isAdmin(), 404);
        $data = $request->validate(['status' => 'required|in:active,suspended']);
        if ($admin->id === $request->user()->id) abort(422, 'You cannot change your own status.');
        $admin->update($data);
        if ($data['status'] !== 'active') $admin->tokens()->delete();
        AuditLogger::log('admin.status_changed', $admin, null, $data);
        return $this->ok($admin, 'Admin updated');
    }

    public function auditLogs(Request $request)
    {
        $q = AuditLog::with('actor:id,name,role')->latest('id');
        if ($request->filled('action')) $q->where('action', 'like', $request->action . '%');
        if ($request->filled('entity_type')) $q->where('entity_type', $request->entity_type);
        return $this->ok($q->paginate($this->perPage()));
    }
}
