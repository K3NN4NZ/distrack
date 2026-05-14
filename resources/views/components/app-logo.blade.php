@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="{{ config('app.name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex shrink-0 items-center">
            <x-app-logo-icon class="h-12 w-auto object-contain lg:h-16" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ config('app.name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex shrink-0 items-center">
            <x-app-logo-icon class="h-14 w-auto object-contain lg:h-16" />
        </x-slot>
    </flux:brand>
@endif
