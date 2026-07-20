@props(['title'])

<x-layouts.app :title="$title">
    <div class="console-shell">
        <aside class="console-sidebar">
            <a class="console-brand" href="{{ route('developer.applications.index') }}">云收款</a>
            <nav class="console-nav" aria-label="主导航">
                <a class="console-nav__link" href="{{ route('developer.applications.index') }}">开发者应用</a>
                @if(auth()->user()?->is_admin)
                    <a class="console-nav__link" href="{{ route('operations.dashboard') }}">运营概览</a>
                    <a class="console-nav__link" href="{{ route('operations.orders.index') }}">本地订单</a>
                    <a class="console-nav__link" href="{{ route('operations.deliveries.index') }}">通知投递</a>
                @endif
            </nav>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="button button--secondary console-logout" type="submit">退出登录</button>
            </form>
        </aside>
        <main class="console-main">
            <header class="console-header">
                <div>
                    <p class="eyebrow">{{ config('app.name') }}</p>
                    <h1 class="console-title">{{ $title }}</h1>
                </div>
                <span class="console-user">{{ auth()->user()?->name }}</span>
            </header>

            @if(session('status'))
                <div class="flash flash--success" role="status">{{ session('status') }}</div>
            @endif

            {{ $slot }}
        </main>
    </div>
</x-layouts.app>
