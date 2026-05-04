<flux:modal
    name="setup-add-crew-modal-{{ $tournament->id }}"
    :show="$show"
    class="max-w-3xl"
>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Add Crew Member') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Publish tournament admins, scorekeepers, or other event staff on the public crew tab.') }}
                </flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost">
                    {{ __('Close') }}
                </flux:button>
            </flux:modal.close>
        </div>

        <form method="POST" action="{{ route('admin.tournaments.crews.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
            <input type="hidden" name="crew_tournament_id" value="{{ $tournament->id }}">
            <input type="hidden" name="redirect_route" value="admin.tournaments.index">
            <input type="hidden" name="redirect_tab" value="crew">

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input name="category" :label="__('Category')" :value="old('category', 'Tournament Admins')" type="text" required />
                <flux:input name="title" :label="__('Title')" :value="old('title')" type="text" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input name="name" :label="__('Name')" :value="old('name')" type="text" required />
                <flux:input name="sort_order" :label="__('Sort Order')" :value="old('sort_order', 1)" type="number" min="1" required />
            </div>

            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Photo Upload') }}
                <input
                    name="photo"
                    type="file"
                    accept="image/*"
                    class="mt-2 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:file:bg-white dark:file:text-zinc-900"
                />
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Optional. Upload a JPG, PNG, SVG, or WEBP image up to 2 MB.') }}
                </p>
                @error('photo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <flux:input name="photo_path" :label="__('Photo URL or Storage Path')" :value="old('photo_path')" type="text" />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Add Crew Member') }}
            </flux:button>
        </form>
    </div>
</flux:modal>
