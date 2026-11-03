<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_redirects_to_the_catalogue(): void
    {
        $this->get('/')->assertRedirect('/products');
    }

    public function test_the_store_pages_load(): void
    {
        $this->get('/login')->assertOk()->assertSee('تسجيل الدخول');
        $this->get('/register')->assertOk()->assertSee('إنشاء حساب جديد');
        $this->get('/account')->assertOk()->assertSee('حسابي');
        $this->get('/products')->assertOk()->assertSee('المنتجات');
        $this->get('/products/1')->assertOk()->assertSee('العودة إلى المنتجات');
        $this->get('/admin/products/create')->assertOk()->assertSee('إضافة منتج جديد');
        $this->get('/cart')->assertOk()->assertSee('ملخص الطلب');
        $this->get('/orders')->assertOk()->assertSee('رقم الطلب');
        $this->get('/orders/1')->assertOk()->assertSee('العودة إلى الطلبات');
    }
}
