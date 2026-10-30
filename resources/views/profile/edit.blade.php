<x-app-layout title="My profile">
    <x-slot name="header">
        <x-page-header title="My profile" subtitle="Your name, email and password." icon="users" :crumbs="['My profile' => null]"/>
    </x-slot>
    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <div class="card p-6">@include('profile.partials.update-profile-information-form')</div>
        <div class="card p-6">@include('profile.partials.update-password-form')</div>
    </div>
</x-app-layout>
