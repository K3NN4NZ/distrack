@php
    $user = auth()->user();
@endphp

<x-layouts::app :title="__('Tournaments')">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">
                @switch(session('status'))
                    @case('tournament-updated')
                        {{ __('Tournament updated successfully.') }}
                        @break
                    @case('tournament-deleted')
                        {{ __('Tournament deleted successfully.') }}
                        @break
                    @case('registration-created')
                        {{ __('Team registered successfully.') }}
                        @break
                    @case('registrations-created')
                        {{ __('Teams registered successfully.') }}
                        @break
                    @default
                        {{ __('Saved.') }}
                @endswitch
            </div>
        @endif

        @include('admin.tournaments.partials.list', [
            'listRoute' => 'admin.tournaments.list',
            'selectionRoute' => null,
            'setupRoute' => 'admin.tournaments.index',
            'setupLabel' => $user->isAdmin() ? __('Setup') : __('Score Matches'),
            'showSetupButton' => true,
            'showRegisterButton' => $user->isAdmin(),
            'showEditButton' => $user->isAdmin(),
            'showDeleteButton' => $user->isAdmin(),
            'description' => $user->isAdmin()
                ? __('Register teams or edit tournament profiles directly here. Use Setup only when you need pitches, matches, or crew tools.')
                : __('Open a tournament to access the match list and launch the live scoring console.'),
        ])
    </div>
</x-layouts::app>
