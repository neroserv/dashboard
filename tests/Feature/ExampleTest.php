<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    // Unauthenticated users are redirected to login
    $response->assertRedirect(route('login'));
});
