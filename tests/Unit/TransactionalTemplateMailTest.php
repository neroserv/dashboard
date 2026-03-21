<?php

use App\Mail\TransactionalTemplateMail;
use App\Models\Brand;

test('transactional mail constrains images in custom brand mail header for outlook', function () {
    $brand = new Brand([
        'name' => 'Neroserv',
        'mail_header' => '<img src="https://cdn.example.com/branding/logo_schrift.png" alt="Logo">',
    ]);

    $html = TransactionalTemplateMail::renderHtml([
        'subject' => 'Test',
        'greeting' => 'Hallo,',
        'body' => 'Inhalt.',
        'action_text' => 'Ticket ansehen',
    ], 'https://example.com/ticket/1', $brand);

    expect($html)->toContain('width="200"')
        ->and($html)->toContain('max-width:200px');
});

test('transactional mail action button uses solid color table layout not linear gradient', function () {
    $html = TransactionalTemplateMail::renderHtml([
        'subject' => 'Test',
        'greeting' => 'Hallo,',
        'body' => 'Inhalt.',
        'action_text' => 'Öffnen',
    ], 'https://example.com/go', null);

    expect($html)->not->toContain('linear-gradient')
        ->and($html)->toContain('bgcolor="#059669"')
        ->and($html)->toContain('background-color:#059669');
});
