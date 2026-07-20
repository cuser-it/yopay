<x-layouts.console title="开发者应用">
    <div class="console-grid console-grid--sidebar">
        <x-ui.card>
            <h2 class="card__title">创建应用</h2>
            <p class="card__description">应用用于隔离 API 凭证、订单和 Webhook。</p>
            <form class="stack-form" method="post" action="{{ route('developer.applications.store') }}">
                @csrf
                <label class="field">
                    <span class="field__label">应用名称</span>
                    <input class="field__input" name="name" value="{{ old('name') }}" required maxlength="120">
                    @error('name')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <label class="field">
                    <span class="field__label">通知 URL 白名单</span>
                    <textarea class="field__textarea" name="allowed_notify_urls" rows="4" placeholder="每行一个 HTTPS 地址">{{ old('allowed_notify_urls') }}</textarea>
                    @error('allowed_notify_urls')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <label class="field">
                    <span class="field__label">返回 URL 白名单</span>
                    <textarea class="field__textarea" name="allowed_return_urls" rows="3" placeholder="每行一个 HTTPS 地址">{{ old('allowed_return_urls') }}</textarea>
                </label>
                <x-ui.button type="submit" :block="true">创建并签发密钥</x-ui.button>
            </form>
        </x-ui.card>

        <section class="console-section">
            <div class="section-heading">
                <div>
                    <h2>我的应用</h2>
                    <p>仅展示本系统开发者接入，不复制易支付商户配置。</p>
                </div>
            </div>
            <div class="resource-list">
                @forelse($applications as $application)
                    <a class="resource-card" href="{{ route('developer.applications.show', $application) }}">
                        <div>
                            <strong>{{ $application->name }}</strong>
                            <span class="resource-card__mono">{{ $application->public_id }}</span>
                        </div>
                        <div class="resource-card__meta">
                            <span>{{ $application->orders_count }} 个订单</span>
                            <span>{{ $application->webhook_endpoints_count }} 个 Webhook</span>
                            <x-ui.badge :tone="$application->status === 'active' ? 'success' : 'error'">{{ $application->status === 'active' ? '启用' : '停用' }}</x-ui.badge>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">尚未创建开发者应用。</div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.console>
