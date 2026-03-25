<?php

use App\Support\RealtimeRegisterPhone;

test('german e164 maps to e164a for realtime register', function () {
    expect(RealtimeRegisterPhone::toVoice('+491727631791', 'DE'))->toBe('+49.1727631791');
});

test('german national number with leading zero', function () {
    expect(RealtimeRegisterPhone::toVoice('01727631791', 'DE'))->toBe('+49.1727631791');
});

test('dutch international matches realtime register documentation style', function () {
    expect(RealtimeRegisterPhone::toVoice('+31384530759', 'NL'))->toBe('+31.384530759');
});

test('us nanp', function () {
    expect(RealtimeRegisterPhone::toVoice('+12025551234', 'US'))->toBe('+1.2025551234');
});

test('empty phone throws', function () {
    RealtimeRegisterPhone::toVoice('', 'DE');
})->throws(InvalidArgumentException::class);
