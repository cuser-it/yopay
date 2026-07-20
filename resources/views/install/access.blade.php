@extends('install.layout', ['title' => '继续安装'])

@section('content')
    <section class="card install-card install-card--narrow">
        <div class="card__header">
            <h2 class="card__title">继续安装</h2>
        </div>

        @include('install.partials.error-summary')

        @if ($tokenAvailable)
            <form method="post" action="{{ route('install.authenticate') }}" class="install-form" data-install-form autocomplete="off">
                <div class="field">
                    <label class="field__label" for="install_token">安装凭证</label>
                    <input class="field__input" id="install_token" name="install_token" type="password" required autocomplete="off" spellcheck="false">
                    @include('install.partials.field-error', ['field' => 'install_token'])
                </div>
                <button class="button button--primary button--block" type="submit" data-submit-label="正在验证…">继续</button>
            </form>
        @else
            <div class="install-command">
                <strong>安装入口尚未启用</strong>
                <p>请由服务器管理员开启安装权限后刷新页面。</p>
            </div>
        @endif
    </section>
@endsection
