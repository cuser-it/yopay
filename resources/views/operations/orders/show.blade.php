<x-layouts.console :title="$order->order_no">
    <div class="metric-grid metric-grid--compact">
        <x-ui.card><p class="eyebrow">状态</p><p class="metric-value"><x-payment-status-badge :status="$order->status" /></p></x-ui.card>
        <x-ui.card><p class="eyebrow">应付金额</p><p class="metric-value"><x-money :cents="$order->expected_amount_cents" /></p></x-ui.card>
        <x-ui.card><p class="eyebrow">实际到账</p><p class="metric-value">@if($order->paid_amount_cents !== null)<x-money :cents="$order->paid_amount_cents" />@else—@endif</p></x-ui.card>
        <x-ui.card><p class="eyebrow">差额</p><p class="metric-value">@if($order->amount_difference_cents !== null)<x-money :cents="$order->amount_difference_cents" />@else—@endif</p></x-ui.card>
    </div>

    <section class="detail-grid">
        <x-ui.card>
            <h2 class="card__title">订单映射</h2>
            <dl class="detail-list">
                <div><dt>来源</dt><dd>{{ $order->source->value }}</dd></div>
                <div><dt>外部订单号</dt><dd>{{ $order->external_order_no ?? '—' }}</dd></div>
                <div><dt>开发者应用</dt><dd>{{ $order->application?->name ?? '—' }}</dd></div>
                <div><dt>支付方式</dt><dd>{{ $order->payment_method?->value ?? '—' }}</dd></div>
                <div><dt>EasyPay 版本</dt><dd>{{ $order->gateway_api_version->value }}</dd></div>
                <div><dt>上游订单号</dt><dd><code>{{ $order->gateway_order_no ?? '—' }}</code></dd></div>
                <div><dt>通道交易号</dt><dd><code>{{ $order->gateway_trade_no ?? '—' }}</code></dd></div>
                <div><dt>支付时间</dt><dd>{{ $order->paid_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
            </dl>
        </x-ui.card>
        <x-ui.card>
            <h2 class="card__title">状态时间线</h2>
            <ol class="timeline">
                @foreach($order->statusEvents as $event)
                    <li><x-payment-status-badge :status="$event->to_status" /><span>{{ $event->source }}</span><time>{{ $event->created_at?->format('Y-m-d H:i:s') }}</time></li>
                @endforeach
            </ol>
        </x-ui.card>
    </section>

    <section class="console-section">
        <div class="section-heading"><div><h2>回调记录</h2><p>保存验签、商户校验和幂等处理结果。</p></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>请求 ID</th><th>版本</th><th>签名</th><th>商户</th><th>结果</th><th>时间</th></tr></thead>
                <tbody>
                @forelse($order->callbacks as $callback)
                    <tr>
                        <td><code>{{ $callback->request_id }}</code></td>
                        <td>{{ $callback->gateway_api_version->value }}</td>
                        <td>{{ $callback->signature_valid ? '通过' : '失败' }}</td>
                        <td>{{ $callback->merchant_valid ? '通过' : '失败' }}</td>
                        <td>{{ $callback->outcome ?? $callback->processing_status }}</td>
                        <td>{{ $callback->received_at?->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">暂无回调记录。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="console-section">
        <div class="section-heading"><div><h2>事件与投递</h2><p>订单事务内创建事件，队列在事务提交后投递。</p></div></div>
        <div class="resource-list">
            @forelse($order->paymentEvents as $event)
                <div class="resource-card resource-card--static">
                    <div><strong>{{ $event->event_type }}</strong><span class="resource-card__mono">{{ $event->event_id }}</span></div>
                    <div class="resource-card__meta"><span>{{ $event->webhookDeliveries->count() }} 个 Webhook</span><span>{{ $event->occurred_at?->format('Y-m-d H:i:s') }}</span></div>
                </div>
            @empty
                <div class="empty-state">暂无支付事件。</div>
            @endforelse
        </div>
        @if($notifications->isNotEmpty())
            <div class="table-wrap console-subsection">
                <table class="data-table"><thead><tr><th>管理员通知</th><th>状态</th><th>尝试</th><th>最近错误</th></tr></thead><tbody>
                @foreach($notifications as $notification)
                    <tr><td>{{ $notification->channel }}</td><td><x-delivery-status-badge :status="$notification->status" /></td><td>{{ $notification->attempt_count }}</td><td>{{ $notification->last_error ?? '—' }}</td></tr>
                @endforeach
                </tbody></table>
            </div>
        @endif
    </section>
</x-layouts.console>
