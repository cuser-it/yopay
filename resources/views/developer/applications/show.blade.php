<x-layouts.console :title="$application->name">
    @if(session('issued_secret'))
        <div class="secret-panel" role="status">
            <strong>API 密钥仅显示一次，请立即保存。</strong>
            <code>Key ID: {{ session('issued_secret.key_id') }}</code>
            <code>Secret: {{ session('issued_secret.secret') }}</code>
        </div>
    @endif
    @if(session('issued_webhook_secret'))
        <div class="secret-panel" role="status">
            <strong>Webhook 签名密钥仅显示一次。</strong>
            <code>{{ session('issued_webhook_secret') }}</code>
        </div>
    @endif

    <div class="metric-grid metric-grid--compact">
        <x-ui.card><p class="eyebrow">App ID</p><p class="metric-value metric-value--code">{{ $application->public_id }}</p></x-ui.card>
        <x-ui.card><p class="eyebrow">状态</p><p class="metric-value">{{ $application->status === 'active' ? '正常' : '停用' }}</p></x-ui.card>
        <x-ui.card><p class="eyebrow">订单</p><p class="metric-value">{{ $application->orders_count }}</p></x-ui.card>
    </div>

    <section class="console-section">
        <div class="section-heading">
            <div><h2>API 凭证</h2><p>请求使用 HMAC-SHA256；轮换会撤销旧密钥。</p></div>
            <form method="post" action="{{ route('developer.applications.credentials.rotate', $application) }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">轮换密钥</x-ui.button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Key ID</th><th>末四位</th><th>状态</th><th>最近使用</th></tr></thead>
                <tbody>
                @foreach($application->credentials as $credential)
                    <tr>
                        <td><code>{{ $credential->key_id }}</code></td>
                        <td><code>••••{{ $credential->secret_last_four }}</code></td>
                        <td><x-ui.badge :tone="$credential->revoked_at ? 'error' : 'success'">{{ $credential->revoked_at ? '已撤销' : '有效' }}</x-ui.badge></td>
                        <td>{{ $credential->last_used_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="console-grid console-grid--sidebar">
        <x-ui.card>
            <h2 class="card__title">新增 Webhook</h2>
            <p class="card__description">仅允许白名单内的公网 HTTPS 地址。</p>
            <form class="stack-form" method="post" action="{{ route('developer.applications.webhooks.store', $application) }}">
                @csrf
                <label class="field"><span class="field__label">名称</span><input class="field__input" name="name" required maxlength="120"></label>
                <label class="field"><span class="field__label">URL</span><input class="field__input" type="url" name="url" required></label>
                <fieldset class="check-grid">
                    <legend class="field__label">订阅事件</legend>
                    @foreach($webhookEvents as $event)
                        <label class="check-row"><input type="checkbox" name="subscribed_events[]" value="{{ $event }}" checked><span>{{ $event }}</span></label>
                    @endforeach
                </fieldset>
                @error('url')<span class="field__error">{{ $message }}</span>@enderror
                <x-ui.button type="submit" :block="true">创建端点</x-ui.button>
            </form>
        </x-ui.card>

        <section class="console-section">
            <div class="section-heading"><div><h2>Webhook 端点</h2><p>密钥用于验证时间戳与原始请求体。</p></div></div>
            <div class="resource-list">
                @forelse($application->webhookEndpoints as $endpoint)
                    <div class="resource-card resource-card--static">
                        <div>
                            <strong>{{ $endpoint->name }}</strong>
                            <span class="resource-card__mono">{{ $endpoint->url }}</span>
                        </div>
                        <div class="resource-card__meta">
                            <x-ui.badge :tone="$endpoint->enabled ? 'success' : 'error'">{{ $endpoint->enabled ? '启用' : '停用' }}</x-ui.badge>
                            @if($endpoint->enabled)
                                <form method="post" action="{{ route('developer.applications.webhooks.disable', [$application, $endpoint->id]) }}">
                                    @csrf @method('delete')
                                    <button class="text-action text-action--danger" type="submit">停用</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">尚未配置 Webhook 端点。</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="console-section">
        <div class="section-heading"><div><h2>最近订单</h2><p>固定金额由 API 签名请求创建，浏览器不可修改。</p></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>订单号</th><th>外部订单号</th><th>金额</th><th>状态</th><th>创建时间</th></tr></thead>
                <tbody>
                @forelse($application->orders as $order)
                    <tr>
                        <td><code>{{ $order->order_no }}</code></td>
                        <td>{{ $order->external_order_no }}</td>
                        <td><x-money :cents="$order->expected_amount_cents" /></td>
                        <td><x-payment-status-badge :status="$order->status" /></td>
                        <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">暂无订单。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="console-section">
        <div class="section-heading"><div><h2>Webhook 投递</h2><p>失败任务按计划重试，也可人工重发。</p></div></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>事件</th><th>状态</th><th>尝试</th><th>下次重试</th><th></th></tr></thead>
                <tbody>
                @forelse($deliveries as $delivery)
                    <tr>
                        <td><code>{{ $delivery->paymentEvent?->event_type }}</code></td>
                        <td><x-delivery-status-badge :status="$delivery->status" /></td>
                        <td>{{ $delivery->attempt_count }}</td>
                        <td>{{ $delivery->next_attempt_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td>
                            @if(in_array($delivery->status, ['failed', 'retrying'], true))
                                <form method="post" action="{{ route('developer.applications.deliveries.retry', [$application, $delivery->id]) }}">
                                    @csrf <button class="text-action" type="submit">重试</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">暂无投递记录。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.console>
