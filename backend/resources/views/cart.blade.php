@extends('layouts.app')

@section('title', 'السلة')

@section('active', 'cart')

@section('content')
<main class="page-main">
    <h1 class="page-title">السلة</h1>

    <div class="alert" id="form-alert" role="alert" hidden></div>
    <p class="empty-state" id="cart-empty" hidden>السلة فارغة. <a href="{{ route('products.index') }}">تصفح المنتجات</a></p>

    <div class="cart-layout" id="cart-layout" hidden>
        <section class="panel cart-items" aria-label="المنتجات في السلة" id="cart-items"></section>

        <aside class="panel summary">
            <h2 class="summary__title">ملخص الطلب</h2>
            <div class="summary__row"><span>عدد القطع</span><span id="cart-count"></span></div>
            <div class="summary__row summary__row--total"><span>الإجمالي</span><span dir="ltr" id="cart-total"></span></div>
            <button type="button" class="btn" id="checkout-button">تأكيد الطلب</button>
            <a href="{{ route('products.index') }}" class="summary__link">متابعة التسوق</a>
        </aside>
    </div>
</main>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    Store.initCart();
</script>
@endpush
