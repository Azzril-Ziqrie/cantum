<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    private const MAX_PER_ACCOUNT = 5;
    private const MAX_PER_IP = 30;
    private const DECAY_SECONDS = 900;

    /** Hash bcrypt tetap untuk menyamakan waktu respons email yang tidak ada. */
    private const DUMMY_HASH = '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    public function create(): View
    {
        return view('staff.login');
    }

    /** Dibatasi per kombinasi email+IP dan per IP, supaya satu akun tidak bisa ditebak dari banyak tempat maupun sebaliknya. */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        $keys = $this->limiterKeys($request, $credentials['email']);

        if ($this->tooManyAttempts($keys)) {
            return back()->withErrors(['email' => 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.'])->onlyInput('email');
        }

        if (!$this->authenticate($credentials)) {
            foreach (array_keys($keys) as $key) {
                RateLimiter::hit($key, self::DECAY_SECONDS);
            }
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }

        foreach (array_keys($keys) as $key) {
            RateLimiter::clear($key);
        }
        $request->session()->regenerate();
        return redirect()->intended(route(Auth::user()->role->landingRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('staff.login');
    }

    /** Email tak dikenal tetap menjalankan hash, supaya waktu respons tidak membocorkan akun mana yang ada. */
    private function authenticate(array $credentials): bool
    {
        if (!User::query()->where('email', $credentials['email'])->exists()) {
            Hash::check($credentials['password'], self::DUMMY_HASH);
            return false;
        }
        return Auth::attempt($credentials + ['is_active' => true]);
    }

    /** @return array<string,int> kunci limiter => batas percobaan */
    private function limiterKeys(Request $request, string $email): array
    {
        return [
            'login:account:' . sha1(strtolower($email) . '|' . $request->ip()) => self::MAX_PER_ACCOUNT,
            'login:ip:' . $request->ip() => self::MAX_PER_IP,
        ];
    }

    private function tooManyAttempts(array $keys): bool
    {
        foreach ($keys as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                return true;
            }
        }
        return false;
    }
}
