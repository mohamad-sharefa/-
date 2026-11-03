<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_the_store_pages_load(): void
    {
        $this->get('/login')->assertOk()->assertSee('تسجيل الدخول');
        $this->get('/register')->assertOk()->assertSee('إنشاء حساب جديد');
        $this->get('/account')->assertOk()->assertSee('حسابي');
    }
}
