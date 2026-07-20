<x-layouts.console title="通知投递">
    <section class="console-section">
        <div class="section-heading"><div><h2>开发者 Webhook</h2><p>仅 HTTP 2xx 视为成功，失败按固定计划重试。</p></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>订单</th><th>事件</th><th>状态</th><th>尝试</th><th>响应</th><th>下次重试</th><th></th></tr></thead>
                <tbody>
                @forelse($webhooks as $delivery)
                    <tr>
                        <td><code>{{ $delivery->paymentEvent?->order?->order_no }}</code></td>
                        <td>{{ $delivery->paymentEvent?->event_type }}</td>
                        <td><x-delivery-status-badge :status="$delivery->status" /></td>
                        <td>{{ $delivery->attempt_count }}</td>
                        <td>{{ $delivery->response_status ?? '—' }}</td>
                        <td>{{ $delivery->next_attempt_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td>@if(in_array($delivery->status, ['failed', 'retrying'], true))<form method="post" action="{{ route('operations.webhook-deliveries.retry', $delivery) }}">@csrf<button class="text-action" type="submit">重试</button></form>@endif</td>
                    </tr>
                @empty<tr><td colspan="7">暂无 Webhook 投递。</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="console-section">
        <div class="section-heading"><div><h2>管理员到账通知</h2><p>通知目标来自服务端配置，失败可重试。</p></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>订单</th><th>通道</th><th>状态</th><th>尝试</th><th>最近错误</th><th></th></tr></thead>
                <tbody>
                @forelse($notifications as $delivery)
                    <tr>
                        <td><code>{{ $delivery->order?->order_no }}</code></td>
                        <td>{{ $delivery->channel }}</td>
                        <td><x-delivery-status-badge :status="$delivery->status" /></td>
                        <td>{{ $delivery->attempt_count }}</td>
                        <td>{{ $delivery->last_error ?? '—' }}</td>
                        <td>@if(in_array($delivery->status, ['failed', 'retrying'], true))<form method="post" action="{{ route('operations.notification-deliveries.retry', $delivery) }}">@csrf<button class="text-action" type="submit">重试</button></form>@endif</td>
                    </tr>
                @empty<tr><td colspan="6">暂无管理员通知。</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.console>
