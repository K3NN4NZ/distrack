@php
    $readonly = $readonly ?? false;
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    @foreach ($scoreSheetConfigs as $sheet)
        @include('shared.partials.match-score-sheet-grid', [
            'sheet' => $sheet,
            'readonly' => $readonly,
        ])
    @endforeach
</div>
