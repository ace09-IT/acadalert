<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_root_redirects_to_the_login_screen(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
