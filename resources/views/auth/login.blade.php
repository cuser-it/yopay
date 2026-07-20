<x-layouts.app title="登录">
    <main class="checkout-shell">
        <x-ui.card class="checkout-card">
            <div class="checkout-intro auth-intro">
                <p class="eyebrow">{{ config('app.name') }}</p>
                <h1 class="checkout-intro__title">登录管理控制台</h1>
                <p class="checkout-intro__copy">管理开发者应用、本地订单、异常和通知投递。</p>
            </div>
            <form class="checkout-form" method="post" action="{{ route('login.store') }}">
                @csrf
                <label class="field">
                    <span class="field__label">邮箱</span>
                    <input class="field__input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                    @error('email')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <label class="field">
                    <span class="field__label">密码</span>
                    <input class="field__input" type="password" name="password" required autocomplete="current-password">
                </label>
                <label class="check-row">
                    <input type="checkbox" name="remember" value="1">
                    <span>保持登录</span>
                </label>
                <x-ui.button type="submit" :block="true">登录</x-ui.button>
            </form>
        </x-ui.card>
    </main>
</x-layouts.app>
