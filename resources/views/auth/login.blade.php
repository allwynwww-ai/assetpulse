<x-guest-layout>
    <h2 class="font-display text-[24px] font-bold tracking-tight text-ink lg:text-[30px]">Welcome back</h2>
    <p class="mt-1.5 text-[14px] text-ink3">Sign in to manage your fixed assets.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-4">
        @csrf
        <div>
            <label for="email" class="mb-1.5 block text-[13px] font-semibold text-ink">Work email</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-ink3"><x-icon name="mail" class="h-[18px] w-[18px]"/></span>
                <input id="email" class="form-input h-12 rounded-xl pl-11 text-[14.5px] shadow-card" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.in">
            </div>
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="text-[13px] font-semibold text-ink">Password</label>
                @if (Route::has('password.request'))
                    <a class="text-[12.5px] font-semibold text-brand-600 hover:underline dark:text-brand-300" href="{{ route('password.request') }}">Forgot password?</a>
                @endif
            </div>
            <x-password-input id="password" name="password" required autocomplete="current-password" icon="lock" input-class="h-12 rounded-xl text-[14.5px] shadow-card" />
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <label for="remember_me" class="flex items-center gap-2.5 text-[13px] text-ink2">
            <input id="remember_me" type="checkbox" class="form-check" name="remember"> Keep me signed in on this device
        </label>
        <button class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 text-[15px] font-semibold text-white shadow-[0_10px_24px_-8px_rgba(72,64,214,.7)] transition hover:bg-brand-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/30">
            Sign in <x-icon name="arrow-right" class="h-[18px] w-[18px]" stroke="2.25"/>
        </button>
    </form>
    <p class="mt-7 text-center text-[12.5px] text-ink3">Accounts are created by your company administrator.</p>
</x-guest-layout>
