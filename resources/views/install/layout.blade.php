<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $title ?? '安装向导' }} · {{ config('app.name', 'Yunvix Payment') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="install-shell">
        <header class="install-header">
            <div>
                <p class="install-eyebrow">Yunvix Payment</p>
                <h1>安全安装向导</h1>
                <p>安装过程不会删除表、覆盖非空数据库或在页面中回显密钥。</p>
            </div>
            <span class="badge badge--info">仅首次安装</span>
        </header>

        @isset($step)
            <ol class="install-steps" aria-label="安装进度">
                @foreach ([1 => '环境检查', 2 => '站点与数据库', 3 => 'EasyPay V2', 4 => '管理员与安装'] as $number => $label)
                    <li class="install-step {{ $step === $number ? 'install-step--active' : '' }} {{ $step > $number ? 'install-step--complete' : '' }}">
                        <span>{{ $step > $number ? '✓' : $number }}</span>
                        <strong>{{ $label }}</strong>
                    </li>
                @endforeach
            </ol>
        @endisset

        @yield('content')

        <footer class="install-footer">
            <span>安装成功后，<code>/install</code> 将由私有锁永久关闭。</span>
            <span>请勿通过截图、工单或聊天工具发送数据库密码与 RSA 私钥。</span>
        </footer>
    </main>
</body>
</html>
