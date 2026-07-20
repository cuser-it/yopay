<x-layouts.console title="本地订单">
    <form class="filter-bar" method="get">
        <label class="field filter-bar__search">
            <span class="field__label">搜索</span>
            <input class="field__input" name="search" value="{{ $search }}" placeholder="订单号、外部订单号或通道交易号">
        </label>
        <label class="field">
            <span class="field__label">状态</span>
            <select class="field__select" name="status">
                <option value="">全部状态</option>
                @foreach($statuses as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->value }}</option>
                @endforeach
            </select>
        </label>
        <x-ui.button type="submit">筛选</x-ui.button>
    </form>

    <section class="console-section">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>订单号</th><th>外部订单号</th><th>来源</th><th>应付</th><th>实收</th><th>状态</th><th>创建时间</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><a href="{{ route('operations.orders.show', $order) }}"><code>{{ $order->order_no }}</code></a></td>
                        <td>{{ $order->external_order_no ?? '—' }}</td>
                        <td>{{ $order->source->value === 'developer_api' ? 'API' : '公开' }}</td>
                        <td><x-money :cents="$order->expected_amount_cents" /></td>
                        <td>@if($order->paid_amount_cents !== null)<x-money :cents="$order->paid_amount_cents" />@else—@endif</td>
                        <td><x-payment-status-badge :status="$order->status" /></td>
                        <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">没有匹配的订单。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="simple-pagination">
            @if($orders->previousPageUrl())<a class="button button--secondary" href="{{ $orders->previousPageUrl() }}">上一页</a>@endif
            <span>第 {{ $orders->currentPage() }} / {{ $orders->lastPage() }} 页</span>
            @if($orders->nextPageUrl())<a class="button button--secondary" href="{{ $orders->nextPageUrl() }}">下一页</a>@endif
        </div>
    </section>
</x-layouts.console>
