@extends('layouts.app')

@section('title', 'تفاصيل المنتج')

@section('active', 'products')

@section('content')
<main class="page-main page-main--narrow-top">
    <a href="{{ route('products.index') }}" class="back-link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
        العودة إلى المنتجات
    </a>

    <p class="empty-state" id="product-missing" hidden>هذا المنتج غير موجود.</p>

    <section class="product-detail" id="product-detail" hidden>
        <div class="product-detail__media" data-product="image"></div>
        <div class="product-detail__info">
            <span class="badge" data-product="category"></span>
            <h1 class="product-detail__name" data-product="name"></h1>
            <span class="product-detail__price" dir="ltr" data-product="price"></span>
            <span class="stock" data-product="stock"></span>
            <div class="product-detail__description">
                <h2>الوصف</h2>
                <p data-product="description"></p>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
    Store.loadProduct(@json($productId));
</script>
@endpush
