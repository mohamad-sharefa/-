@extends('layouts.app')

@section('title', 'الطلبات')

@section('active', 'orders')

@section('content')
<main class="page-main">
    <h1 class="page-title" id="orders-title">طلباتي</h1>

    <p class="empty-state" id="orders-empty" hidden>لا توجد طلبات بعد. <a href="{{ route('products.index') }}">تصفح المنتجات</a></p>

    <div class="table-wrap" id="orders-table" hidden>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">رقم الطلب</th>
                    <th scope="col" data-admin-col hidden>الزبون</th>
                    <th scope="col">التاريخ</th>
                    <th scope="col">المنتجات</th>
                    <th scope="col">الإجمالي</th>
                    <th scope="col">الحالة</th>
                    <th scope="col"><span class="visually-hidden">إجراء</span></th>
                </tr>
            </thead>
            <tbody id="orders-body"></tbody>
        </table>
    </div>

    <nav class="pagination" aria-label="الصفحات" id="pagination"></nav>
</main>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    Store.loadOrders();
</script>
@endpush
