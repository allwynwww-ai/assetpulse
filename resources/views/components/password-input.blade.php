{{--
    Password field with a show/hide toggle (eye icon inside the right edge).
    Usage: <x-password-input id="password" name="password" autocomplete="current-password" required />
    Any "class" passed in is applied to the wrapper (spacing/width); the input itself
    always uses the app's .form-input style plus right padding for the toggle.
--}}
@props(['disabled' => false, 'inputClass' => ''])

@php
    $inputId = $attributes->get('id');
@endphp

<div x-data="{ show: false }"
     x-init="$refs.input.form && $refs.input.form.addEventListener('submit', () => show = false)"
     {{ $attributes->only('class')->merge(['class' => 'relative']) }}>
    <input type="password"
           x-ref="input"
           :type="show ? 'text' : 'password'"
           @disabled($disabled)
           autocapitalize="none" autocorrect="off" spellcheck="false"
           {{ $attributes->except('class')->merge(['class' => trim('form-input pr-11 '.$inputClass)]) }}>
    <button type="button"
            class="absolute -inset-y-px right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-400 transition hover:text-slate-600 focus:outline-none focus-visible:text-brand-600 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-400/60 disabled:cursor-not-allowed disabled:opacity-50"
            data-password-toggle
            @click="show = ! show"
            :aria-label="show ? 'Hide password' : 'Show password'"
            :title="show ? 'Hide password' : 'Show password'"
            :aria-pressed="show.toString()"
            aria-label="Show password"
            title="Show password"
            aria-pressed="false"
            @if ($inputId) aria-controls="{{ $inputId }}" @endif
            @disabled($disabled)>
        {{-- eye: password hidden → click to show --}}
        <svg x-show="! show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        {{-- eye-slash: password shown → click to hide --}}
        <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>
    </button>
</div>
