@extends('install.layout', ['title' => '管理员与安装', 'step' => 4])

@section('content')
    <section class="install-review-grid">
        <div class="card install-card">
            <div class="card__header">
                <h2 class="card__title">创建首个管理员</h2>
                <p class="card__description">确认后生成 APP_KEY、执行迁移和初始化，并写入永久安装锁。</p>
            </div>

            @include('install.partials.error-summary')

            <form method="post" action="{{ route('install.complete') }}" class="install-form" data-install-form autocomplete="off">
                <input type="hidden" name="_install_csrf" value="{{ $csrf }}">

                <div class="field">
                    <label class="field__label" for="admin_name">管理员姓名</label>
                    <input class="field__input" id="admin_name" name="admin_name" value="{{ $values['admin_name'] ?? '' }}" required maxlength="80" autocomplete="name">
                    @include('install.partials.field-error', ['field' => 'admin_name'])
                </div>
                <div class="field">
                    <label class="field__label" for="admin_email">管理员邮箱</label>
                    <input class="field__input" id="admin_email" name="admin_email" type="email" value="{{ $values['admin_email'] ?? '' }}" required maxlength="255" autocomplete="email">
                    @include('install.partials.field-error', ['field' => 'admin_email'])
                </div>
                <div class="install-form-grid">
                    <div class="field">
                        <label class="field__label" for="admin_password">管理员密码</label>
                        <input class="field__input" id="admin_password" name="admin_password" type="password" required autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label class="field__label" for="admin_password_confirmation">确认密码</label>
                        <input class="field__input" id="admin_password_confirmation" name="admin_password_confirmation" type="password" required autocomplete="new-password">
                    </div>
                </div>
                <span class="field__hint">至少 12 位，并包含大小写字母、数字和符号。</span>
                @include('install.partials.field-error', ['field' => 'admin_password'])

                <label class="install-confirmation">
                    <input type="checkbox" name="confirm_empty_database" value="1" required>
                    <span>我确认目标数据库是专用于本系统的空库，并理解迁移开始后失败时需要手工恢复空库。</span>
                </label>
                @include('install.partials.field-error', ['field' => 'confirm_empty_database'])
                @include('install.partials.field-error', ['field' => 'installation'])

                <div class="install-actions">
                    <a class="button button--secondary" href="{{ route('install.easypay') }}">返回</a>
                    <button class="button button--primary" type="submit" data-submit-label="正在安全安装…">执行安装</button>
                </div>
            </form>
        </div>

        <aside class="card install-summary">
            <h2 class="card__title">安装摘要</h2>
            <dl class="install-summary-list">
                <div><dt>站点</dt><dd>{{ $summary['site_name'] }}</dd></div>
                <div><dt>地址</dt><dd>{{ $summary['app_url'] }}</dd></div>
                <div><dt>数据库</dt><dd>{{ $summary['database'] }}</dd></div>
                <div><dt>EasyPay 商户</dt><dd>{{ $summary['merchant_id'] }}</dd></div>
                <div><dt>EasyPay 地址</dt><dd>{{ $summary['easypay_url'] }}</dd></div>
            </dl>
            <div class="install-alert install-alert--warning">
                <strong>不会显示的敏感信息</strong>
                <p>数据库密码、RSA 私钥、APP_KEY 和管理员密码不会出现在本摘要或成功页。</p>
            </div>
        </aside>
    </section>
@endsection
