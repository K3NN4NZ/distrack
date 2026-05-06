@php
    $pitchModalRedirectTab = $redirectTab ?? 'pitches';
@endphp
<flux:modal
    name="setup-add-pitch-modal-{{ $tournament->id }}"
    :show="$show"
    class="max-w-2xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Add Pitch') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Attach playable fields to :tournament.', ['tournament' => $tournament->name]) }}
                </flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost">
                    {{ __('Close') }}
                </flux:button>
            </flux:modal.close>
        </div>

        <form method="POST" action="{{ route('admin.tournaments.pitches.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
            <input type="hidden" name="pitch_tournament_id" value="{{ $tournament->id }}">
            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
            <input type="hidden" name="redirect_tab" value="{{ $pitchModalRedirectTab }}">

            <flux:input name="name" :label="__('Pitch Name')" :value="old('name')" type="text" required />
            <flux:input name="location" :label="__('Location')" :value="old('location')" type="text" />
            <flux:input name="sort_order" :label="__('Sort Order')" :value="old('sort_order', 1)" type="number" min="1" required />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Add Pitch') }}
            </flux:button>
        </form>
    </div>
</flux:modal>
