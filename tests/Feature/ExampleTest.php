<?php

test('home requires authentication', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
