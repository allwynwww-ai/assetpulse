<x-guest-layout>
    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Sign in</h2>
    <p class="mt-1 text-sm text-slate-500">Welcome back. Use your company email to continue.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" class="form-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.in">
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="form-label">Password</label>
                @if (Route::has('password.request'))
                    <a class="mb-1.5 text-xs font-medium text-brand-600 hover:underline" href="{{ route('password.request') }}">Forgot?</a>
                @endif
            </div>
            <input id="password" class="form-input" type="password" name="password" required autocomplete="current-password">
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <label for="remember_me" class="flex items-center gap-2 text-sm text-slate-600">
            <input id="remember_me" type="checkbox" class="form-check" name="remember"> Keep me signed in
        </label>
        <button class="btn-primary w-full py-2.5">Sign in</button>
    </form>
    <p class="mt-8 text-center text-xs text-slate-400">Accounts are created by your company administrator.</p>
</x-guest-layout>
