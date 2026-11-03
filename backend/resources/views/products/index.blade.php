@extends('layouts.app')

@section('title', 'المنتجات')

@section('active', 'products')

@section('content')
<main class="page-main">
    <div class="page-head">
        <h1 class="page-title">المنتجات</h1>
        <span class="muted" id="products-count"></span>
    </div>

    <div class="chips" role="group" aria-label="التصنيفات" id="category-filter"></div>

    <div class="product-grid" id="product-grid" aria-live="polite"></div>
    <p class="empty-state" id="products-empty" hidden>لا توجد منتجات في هذا التصنيف.</p>

    <nav class="pagination" aria-label="الصفحات" id="pagination"></nav>
</main>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    Store.loadProducts();
</script>
@endpush
