<x-layouts.console title="运营概览">
    <div class="metric-grid">
        <x-ui.card><p class="eyebrow">今日已支付</p><p class="metric-value">{{ $metrics['paid_today'] }}</p></x-ui.card>
        <x-ui.card><p class="eyebrow">待处理异常</p><p class="metric-value">{{ $metrics['abnormal_open'] }}</p></x-ui.card>
        <x-ui.card><p class="eyebrow">Webhook 失败</p><p class="metric-value">{{ $metrics['webhook_failures'] }}</p></x-ui.card>
        <x-ui.card><p class="eyebrow">管理员通知失败</p><p class="metric-value">{{ $metrics['notification_failures'] }}</p></x-ui.card>
    </div>

    <section class="console-section">
        <div class="section-heading">
            <div><h2>最近本地订单</h2><p>仅展示本系统创建或接入的业务订单。</p></div>
            <a class="button button--secondary" href="{{ route('operations.orders.index') }}">查看全部</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>订单号</th><th>来源</th><th>应付</th><th>实收</th><th>状态</th><th>时间</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><a href="{{ route('operations.orders.show', $order) }}"><code>{{ $order->order_no }}</code></a></td>
                        <td>{{ $order->source->value === 'developer_api' ? '开发者 API' : '公开收款' }}</td>
                        <td><x-money :cents="$order->expected_amount_cents" /></td>
                        <td>{{ $order->paid_amount_cents === null ? '—' : '' }}@if($order->paid_amount_cents !== null)<x-money :cents="$order->paid_amount_cents" />@endif</td>
                        <td><x-payment-status-badge :status="$order->status" /></td>
                        <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">暂无本地订单。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.console>
