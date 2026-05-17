<?php

use App\Support\Utf8Text;

test('fixMojibake repairs common spanish characters', function () {
    expect(Utf8Text::fixMojibake('BraÃ±a'))->toBe('Braña')
        ->and(Utf8Text::fixMojibake('JosÃ©'))->toBe('José')
        ->and(Utf8Text::fixMojibake('NiÃ±o'))->toBe('Niño');
});

test('prepareForStorage normalizes valid utf8 without altering it', function () {
    expect(Utf8Text::prepareForStorage('Braña'))->toBe('Braña')
        ->and(Utf8Text::prepareForStorage(null))->toBeNull()
        ->and(Utf8Text::prepareForStorage(''))->toBe('');
});

test('looksLikeMojibake detects corrupted sequences', function () {
    expect(Utf8Text::looksLikeMojibake('BraÃ±a'))->toBeTrue()
        ->and(Utf8Text::looksLikeMojibake('Braña'))->toBeFalse();
});
