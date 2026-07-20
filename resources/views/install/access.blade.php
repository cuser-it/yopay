@extends('install.layout', ['title' => '验证安装权限'])

@section('content')
    <section class="card install-card install-card--narrow">
        <div class="card__header">
            <h2 class="card__title">验证服务器安装令牌</h2>
            <p class="card__description">安装入口不对公网匿名开放。令牌只应从服务器终端获取。</p>
        </div>

        @include('install.partials.error-summary')

        @if ($tokenAvailable)
            <form method="post" action="{{ route('install.authenticate') }}" class="install-form" data-install-form autocomplete="off">
                <div class="field">
                    <label class="field__label" for="install_token">安装令牌</label>
                    <input class="field__input" id="install_token" name="install_token" type="password" required autocomplete="off" spellcheck="false">
                    <span class="field__hint">令牌验证后仅建立 30 分钟的签名安装会话。</span>
                    @include('install.partials.field-error', ['field' => 'install_token'])
                </div>
                <button class="button button--primary button--block" type="submit" data-submit-label="正在验证…">验证并继续</button>
            </form>
        @else
            <div class="install-command">
                <strong>先在服务器终端生成一次性令牌</strong>
                <code>php artisan install:token</code>
                <p>命令只显示一次明文令牌，服务器仅保存其 SHA-256 摘要。</p>
            </div>
        @endif
    </section>
@endsection
