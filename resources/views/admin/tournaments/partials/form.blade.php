@props([
    'action',
    'method' => 'POST',
    'submitLabel',
    'fieldPrefix' => '',
    'defaults' => [],
    'hiddenFields' => [],
])

@php
    $fieldName = fn (string $name): string => $fieldPrefix.$name;

    $fieldValue = function (string $name, mixed $fallback = '') use ($fieldPrefix, $defaults) {
        $value = old($fieldPrefix.$name, $defaults[$name] ?? $fallback);

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d\TH:i')
            : $value;
    };

    $buildRows = function (
        string $labelKey,
        string $valueKey,
        string $defaultItemsKey,
        string $defaultValueAttribute = 'value',
    ) use ($fieldPrefix, $defaults): array {
        $oldLabels = old($fieldPrefix.$labelKey);
        $oldValues = old($fieldPrefix.$valueKey);

        if (is_array($oldLabels) || is_array($oldValues)) {
            $labels = is_array($oldLabels) ? $oldLabels : [];
            $values = is_array($oldValues) ? $oldValues : [];
        } else {
            $labels = collect($defaults[$defaultItemsKey] ?? [])
                ->map(fn (array $item): string => (string) ($item['label'] ?? ''))
                ->all();
            $values = collect($defaults[$defaultItemsKey] ?? [])
                ->map(fn (array $item): string => (string) ($item[$defaultValueAttribute] ?? ''))
                ->all();
        }

        return collect(range(0, max(count($labels), count($values), 1) - 1))
            ->map(fn (int $index): array => [
                'label' => (string) ($labels[$index] ?? ''),
                'value' => (string) ($values[$index] ?? ''),
            ])
            ->values()
            ->all();
    };

    $infoItems = $buildRows('info_labels', 'info_values', 'info_items');
    $organizerItems = $buildRows('organizer_labels', 'organizer_values', 'organizer_items');
    $linkItems = $buildRows('link_labels', 'link_urls', 'link_items', 'href');
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="space-y-4"
    x-data="{
        provincesUrl: @js(route('locations.provinces')),
        citiesUrl: @js(route('locations.cities')),
        barangaysUrl: @js(route('locations.barangays')),
        infoLabelField: @js($fieldName('info_labels')),
        infoValueField: @js($fieldName('info_values')),
        organizerLabelField: @js($fieldName('organizer_labels')),
        organizerValueField: @js($fieldName('organizer_values')),
        linkLabelField: @js($fieldName('link_labels')),
        linkUrlField: @js($fieldName('link_urls')),
        provinces: [],
        cities: [],
        barangays: [],
        provinceCode: @js((string) $fieldValue('province_code')),
        cityCode: @js((string) $fieldValue('city_code')),
        barangayCode: @js((string) $fieldValue('barangay_code')),
        provinceName: @js((string) $fieldValue('province')),
        cityName: @js((string) $fieldValue('city')),
        barangayName: @js((string) $fieldValue('barangay')),
        countryName: @js((string) $fieldValue('country_name', 'Philippines')),
        infoItems: @js($infoItems),
        organizerItems: @js($organizerItems),
        linkItems: @js($linkItems),
        async init() {
            await this.loadProvinces();

            if (this.provinceCode) {
                await this.loadCities();
            }

            if (this.cityCode) {
                await this.loadBarangays();
            }

            this.syncSelectedNames();

            if (this.infoItems.length === 0) {
                this.infoItems = [{ label: '', value: '' }];
            }

            if (this.organizerItems.length === 0) {
                this.organizerItems = [{ label: '', value: '' }];
            }

            if (this.linkItems.length === 0) {
                this.linkItems = [{ label: '', value: '' }];
            }
        },
        async fetchOptions(url, params = {}) {
            const resource = new URL(url, window.location.origin);

            Object.entries(params).forEach(([key, value]) => {
                if (value) {
                    resource.searchParams.set(key, value);
                }
            });

            const response = await fetch(resource, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                return [];
            }

            const payload = await response.json();

            return Array.isArray(payload.data) ? payload.data : [];
        },
        findName(options, code) {
            return options.find((option) => option.code === code)?.name ?? '';
        },
        findCode(options, name) {
            const target = (name ?? '').trim().toLowerCase();

            return options.find((option) => (option.name ?? '').trim().toLowerCase() === target)?.code ?? '';
        },
        syncSelectedNames() {
            this.provinceName = this.findName(this.provinces, this.provinceCode) || this.provinceName;
            this.cityName = this.findName(this.cities, this.cityCode) || this.cityName;
            this.barangayName = this.findName(this.barangays, this.barangayCode) || this.barangayName;
        },
        async loadProvinces() {
            this.provinces = await this.fetchOptions(this.provincesUrl);

            if (!this.provinceCode && this.provinceName) {
                this.provinceCode = this.findCode(this.provinces, this.provinceName);
            }

            this.provinceName = this.findName(this.provinces, this.provinceCode) || this.provinceName;
        },
        async loadCities() {
            this.cities = this.provinceCode
                ? await this.fetchOptions(this.citiesUrl, { province_code: this.provinceCode })
                : [];

            if (!this.cityCode && this.cityName) {
                this.cityCode = this.findCode(this.cities, this.cityName);
            }

            this.cityName = this.findName(this.cities, this.cityCode) || this.cityName;
        },
        async loadBarangays() {
            this.barangays = this.cityCode
                ? await this.fetchOptions(this.barangaysUrl, { city_code: this.cityCode })
                : [];

            if (!this.barangayCode && this.barangayName) {
                this.barangayCode = this.findCode(this.barangays, this.barangayName);
            }

            this.barangayName = this.findName(this.barangays, this.barangayCode) || this.barangayName;
        },
        async handleProvinceChange() {
            this.provinceName = this.findName(this.provinces, this.provinceCode);
            this.cityCode = '';
            this.cityName = '';
            this.barangayCode = '';
            this.barangayName = '';
            this.barangays = [];

            await this.loadCities();
        },
        async handleCityChange() {
            this.cityName = this.findName(this.cities, this.cityCode);
            this.barangayCode = '';
            this.barangayName = '';

            await this.loadBarangays();
        },
        handleBarangayChange() {
            this.barangayName = this.findName(this.barangays, this.barangayCode);
        },
        addInfoItem() {
            this.infoItems.push({ label: '', value: '' });
        },
        removeInfoItem(index) {
            if (this.infoItems.length === 1) {
                this.infoItems = [{ label: '', value: '' }];

                return;
            }

            this.infoItems.splice(index, 1);
        },
        addOrganizerItem() {
            this.organizerItems.push({ label: '', value: '' });
        },
        removeOrganizerItem(index) {
            if (this.organizerItems.length === 1) {
                this.organizerItems = [{ label: '', value: '' }];

                return;
            }

            this.organizerItems.splice(index, 1);
        },
        addLinkItem() {
            this.linkItems.push({ label: '', value: '' });
        },
        removeLinkItem(index) {
            if (this.linkItems.length === 1) {
                this.linkItems = [{ label: '', value: '' }];

                return;
            }

            this.linkItems.splice(index, 1);
        },
    }"
    x-init="init()"
>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    @foreach ($hiddenFields as $hiddenFieldName => $hiddenFieldValue)
        <input type="hidden" name="{{ $hiddenFieldName }}" value="{{ $hiddenFieldValue }}">
    @endforeach

    <flux:input name="{{ $fieldName('name') }}" :label="__('Tournament Name')" :value="$fieldValue('name')" type="text" required />
    <flux:input name="{{ $fieldName('venue') }}" :label="__('Venue')" :value="$fieldValue('venue')" type="text" required />

    <div class="space-y-4 rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
        <div>
            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Tournament Address') }}</h3>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Use the Philippine PSGC dataset so the public info tab can render a complete barangay-to-province address dynamically.') }}
            </p>
        </div>

        <input type="hidden" name="{{ $fieldName('province') }}" x-bind:value="provinceName">
        <input type="hidden" name="{{ $fieldName('city') }}" x-bind:value="cityName">
        <input type="hidden" name="{{ $fieldName('barangay') }}" x-bind:value="barangayName">
        <input type="hidden" name="{{ $fieldName('country_name') }}" x-bind:value="countryName">

        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Province') }}
                <select
                    name="{{ $fieldName('province_code') }}"
                    x-model="provinceCode"
                    x-on:change="handleProvinceChange"
                    required
                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                >
                    <option value="">{{ __('Select province') }}</option>
                    <template x-for="province in provinces" :key="province.code">
                        <option x-bind:value="province.code" x-text="province.name"></option>
                    </template>
                </select>
                @error($fieldName('province_code'))
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error($fieldName('province'))
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Municipality / City') }}
                <select
                    name="{{ $fieldName('city_code') }}"
                    x-model="cityCode"
                    x-on:change="handleCityChange"
                    x-bind:disabled="cities.length === 0"
                    required
                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:disabled:bg-zinc-900 dark:disabled:text-zinc-500"
                >
                    <option value="">{{ __('Select municipality or city') }}</option>
                    <template x-for="city in cities" :key="city.code">
                        <option x-bind:value="city.code" x-text="city.name"></option>
                    </template>
                </select>
                @error($fieldName('city_code'))
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error($fieldName('city'))
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Barangay') }}
                <select
                    name="{{ $fieldName('barangay_code') }}"
                    x-model="barangayCode"
                    x-on:change="handleBarangayChange"
                    x-bind:disabled="barangays.length === 0"
                    required
                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-neutral-700 dark:bg-zinc-950 dark:text-white dark:disabled:bg-zinc-900 dark:disabled:text-zinc-500"
                >
                    <option value="">{{ __('Select barangay') }}</option>
                    <template x-for="barangay in barangays" :key="barangay.code">
                        <option x-bind:value="barangay.code" x-text="barangay.name"></option>
                    </template>
                </select>
                @error($fieldName('barangay_code'))
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error($fieldName('barangay'))
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Country') }}
                <input
                    type="text"
                    x-model="countryName"
                    readonly
                    class="mt-2 w-full rounded-lg border border-neutral-300 bg-zinc-100 px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-900 dark:text-white"
                >
                @error($fieldName('country_name'))
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </label>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:input name="{{ $fieldName('timezone') }}" :label="__('Timezone')" :value="$fieldValue('timezone', config('app.timezone'))" type="text" />
        <flux:input name="{{ $fieldName('venue_google_map_link') }}" :label="__('Venue Google Map Link')" :value="$fieldValue('venue_google_map_link')" type="text" />
    </div>

    <flux:input name="{{ $fieldName('thumbnail_path') }}" :label="__('Thumbnail URL or Storage Path')" :value="$fieldValue('thumbnail_path')" type="text" />

    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
        {{ __('Description') }}
        <textarea
            name="{{ $fieldName('description') }}"
            rows="4"
            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
        >{{ $fieldValue('description') }}</textarea>
        @error($fieldName('description'))
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </label>

    <div class="space-y-4 rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Organizer Details') }}</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Add public contact rows for the Organizer accordion, like hotline, email, or registration desk hours.') }}
                </p>
            </div>

            <button
                type="button"
                x-on:click="addOrganizerItem"
                class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                {{ __('Add Organizer Row') }}
            </button>
        </div>

        <div class="space-y-3">
            <template x-for="(item, index) in organizerItems" :key="`organizer-${index}`">
                <div class="grid gap-3 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto] md:items-start">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        <span class="block">{{ __('Label') }}</span>
                        <input
                            x-model="item.label"
                            x-bind:name="`${organizerLabelField}[${index}]`"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            placeholder="{{ __('e.g. Contact Email') }}"
                        >
                    </label>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        <span class="block">{{ __('Value') }}</span>
                        <input
                            x-model="item.value"
                            x-bind:name="`${organizerValueField}[${index}]`"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            placeholder="{{ __('e.g. events@distrack.test') }}"
                        >
                    </label>

                    <div class="pt-0 md:pt-7">
                        <button
                            type="button"
                            x-on:click="removeOrganizerItem(index)"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:bg-zinc-900 dark:text-red-300 dark:hover:bg-red-950/40"
                        >
                            {{ __('Remove') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>

        @if ($errors->has($fieldName('organizer_labels')) || $errors->has($fieldName('organizer_values')) || $errors->has($fieldName('organizer_labels.*')) || $errors->has($fieldName('organizer_values.*')))
            <p class="text-sm text-red-600">
                {{ __('Each organizer row needs both a label and a value.') }}
            </p>
        @endif
    </div>

    <div class="space-y-4 rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Public Links') }}</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Add any public URLs that should show in the Public Links accordion, like registration forms, event pages, or livestreams.') }}
                </p>
            </div>

            <button
                type="button"
                x-on:click="addLinkItem"
                class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                {{ __('Add Link Row') }}
            </button>
        </div>

        <div class="space-y-3">
            <template x-for="(item, index) in linkItems" :key="`link-${index}`">
                <div class="grid gap-3 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto] md:items-start">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        <span class="block">{{ __('Label') }}</span>
                        <input
                            x-model="item.label"
                            x-bind:name="`${linkLabelField}[${index}]`"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            placeholder="{{ __('e.g. Event Facebook Page') }}"
                        >
                    </label>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        <span class="block">{{ __('URL') }}</span>
                        <input
                            x-model="item.value"
                            x-bind:name="`${linkUrlField}[${index}]`"
                            type="url"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            placeholder="{{ __('https://example.com/register') }}"
                        >
                    </label>

                    <div class="pt-0 md:pt-7">
                        <button
                            type="button"
                            x-on:click="removeLinkItem(index)"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:bg-zinc-900 dark:text-red-300 dark:hover:bg-red-950/40"
                        >
                            {{ __('Remove') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>

        @if ($errors->has($fieldName('link_labels')) || $errors->has($fieldName('link_urls')) || $errors->has($fieldName('link_labels.*')) || $errors->has($fieldName('link_urls.*')))
            <p class="text-sm text-red-600">
                {{ __('Each public link row needs both a label and a URL.') }}
            </p>
        @endif
    </div>

    <div class="space-y-4 rounded-xl border border-neutral-200 bg-zinc-50 p-4 dark:border-neutral-700 dark:bg-zinc-950">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Additional Public Info') }}</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Add custom label/value rows that should appear on the public info tab without hard-coding them in the Blade template.') }}
                </p>
            </div>

            <button
                type="button"
                x-on:click="addInfoItem"
                class="inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-neutral-400 hover:bg-zinc-100 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                {{ __('Add Info Row') }}
            </button>
        </div>

        <div class="space-y-3">
            <template x-for="(item, index) in infoItems" :key="`info-${index}`">
                <div class="grid gap-3 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto] md:items-start">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        <span class="block">{{ __('Label') }}</span>
                        <input
                            x-model="item.label"
                            x-bind:name="`${infoLabelField}[${index}]`"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            placeholder="{{ __('e.g. Tournament hotline') }}"
                        >
                    </label>

                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        <span class="block">{{ __('Value') }}</span>
                        <input
                            x-model="item.value"
                            x-bind:name="`${infoValueField}[${index}]`"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
                            placeholder="{{ __('e.g. 26-player cap per team') }}"
                        >
                    </label>

                    <div class="pt-0 md:pt-7">
                        <button
                            type="button"
                            x-on:click="removeInfoItem(index)"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 dark:border-red-900/70 dark:bg-zinc-900 dark:text-red-300 dark:hover:bg-red-950/40"
                        >
                            {{ __('Remove') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>

        @if ($errors->has($fieldName('info_labels')) || $errors->has($fieldName('info_values')) || $errors->has($fieldName('info_labels.*')) || $errors->has($fieldName('info_values.*')))
            <p class="text-sm text-red-600">
                {{ __('Each custom info row needs both a label and a value.') }}
            </p>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:input name="{{ $fieldName('registration_deadline') }}" :label="__('Registration Deadline')" :value="$fieldValue('registration_deadline')" type="datetime-local" />
        <flux:input name="{{ $fieldName('starts_at') }}" :label="__('Starts At')" :value="$fieldValue('starts_at')" type="datetime-local" />
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ __('Event Type') }}
            <select
                name="{{ $fieldName('event_type') }}"
                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
            >
                <option value="">{{ __('Select event type') }}</option>
                @foreach (['Tournament', 'League', 'Hat', 'Cup', 'Clinic'] as $value)
                    <option value="{{ $value }}" @selected((string) $fieldValue('event_type') === $value)>{{ __($value) }}</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ __('Division') }}
            <select
                name="{{ $fieldName('division') }}"
                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
            >
                <option value="">{{ __('Select division') }}</option>
                @foreach (['Mix', 'Men', 'Women', 'Open'] as $value)
                    <option value="{{ $value }}" @selected((string) $fieldValue('division') === $value)>{{ __($value) }}</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ __('Surface') }}
            <select
                name="{{ $fieldName('surface') }}"
                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
            >
                <option value="">{{ __('Select surface') }}</option>
                @foreach (['Outdoor', 'Indoor', 'Beach'] as $value)
                    <option value="{{ $value }}" @selected((string) $fieldValue('surface') === $value)>{{ __($value) }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:input name="{{ $fieldName('ends_at') }}" :label="__('Ends At')" :value="$fieldValue('ends_at')" type="datetime-local" />

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ __('Status') }}
            <select
                name="{{ $fieldName('status') }}"
                class="mt-2 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-neutral-700 dark:bg-zinc-950 dark:text-white"
            >
                @foreach (['draft' => 'Draft', 'registration' => 'Registration', 'live' => 'Live', 'completed' => 'Completed'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) $fieldValue('status', 'draft') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
            @error($fieldName('status'))
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </label>
    </div>

    <label class="flex items-start gap-3 rounded-lg border border-neutral-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-neutral-700 dark:bg-zinc-950 dark:text-zinc-300">
        <input type="hidden" name="{{ $fieldName('is_public') }}" value="0">
        <input
            type="checkbox"
            name="{{ $fieldName('is_public') }}"
            value="1"
            @checked((bool) $fieldValue('is_public', true))
            class="mt-1 rounded border-neutral-300 text-zinc-900 focus:ring-zinc-500"
        >
        <span>
            <span class="block font-medium text-zinc-900 dark:text-white">{{ __('Publish on public board') }}</span>
            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Enable this to expose the tournament on the public discovery page and detail route.') }}
            </span>
        </span>
    </label>

    <flux:button type="submit" variant="primary" class="w-full">
        {{ $submitLabel }}
    </flux:button>
</form>
