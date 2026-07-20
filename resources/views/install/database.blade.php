@extends('install.layout', ['title' => '站点与数据库', 'step' => 2])

@section('content')
    <section class="card install-card">
        <div class="card__header">
            <h2 class="card__title">站点与 MySQL 配置</h2>
            <p class="card__description">提交后会真实测试连接，并确认目标数据库完全为空。</p>
        </div>

        @include('install.partials.error-summary')

        <form method="post" action="{{ route('install.database.store') }}" class="install-form" data-install-form autocomplete="off">
            <input type="hidden" name="_install_csrf" value="{{ $csrf }}">

            <div class="install-form-grid">
                <div class="field">
                    <label class="field__label" for="site_name">站点名称</label>
                    <input class="field__input" id="site_name" name="site_name" value="{{ $values['site_name'] ?? '' }}" required maxlength="80">
                    @include('install.partials.field-error', ['field' => 'site_name'])
                </div>
                <div class="field">
                    <label class="field__label" for="app_url">站点地址</label>
                    <input class="field__input" id="app_url" name="app_url" type="url" value="{{ $values['app_url'] ?? '' }}" required maxlength="255" placeholder="https://pay.example.com">
                    @include('install.partials.field-error', ['field' => 'app_url'])
                </div>
                <div class="field">
                    <label class="field__label" for="db_host">MySQL 主机</label>
                    <input class="field__input" id="db_host" name="db_host" value="{{ $values['db_host'] ?? '127.0.0.1' }}" required maxlength="255" autocomplete="off">
                    @include('install.partials.field-error', ['field' => 'db_host'])
                </div>
                <div class="field">
                    <label class="field__label" for="db_port">MySQL 端口</label>
                    <input class="field__input" id="db_port" name="db_port" type="number" min="1" max="65535" value="{{ $values['db_port'] ?? 3306 }}" required>
                    @include('install.partials.field-error', ['field' => 'db_port'])
                </div>
                <div class="field">
                    <label class="field__label" for="db_database">空数据库名称</label>
                    <input class="field__input" id="db_database" name="db_database" value="{{ $values['db_database'] ?? '' }}" required maxlength="64" autocomplete="off">
                    @include('install.partials.field-error', ['field' => 'db_database'])
                </div>
                <div class="field">
                    <label class="field__label" for="db_username">数据库用户名</label>
                    <input class="field__input" id="db_username" name="db_username" value="{{ $values['db_username'] ?? '' }}" required maxlength="128" autocomplete="off">
                    @include('install.partials.field-error', ['field' => 'db_username'])
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="db_password">数据库密码</label>
                <input class="field__input" id="db_password" name="db_password" type="password" maxlength="1024" autocomplete="new-password">
                <span class="field__hint">密码只写入服务器私有的 <code>.env</code>，验证失败时不会回显。</span>
                @include('install.partials.field-error', ['field' => 'db_password'])
                @include('install.partials.field-error', ['field' => 'database'])
            </div>

            <div class="install-alert install-alert--warning">
                <strong>数据库安全策略</strong>
                <p>只接受已存在的空数据库。检测到任何表都会终止，不执行 <code>migrate:fresh</code>、DROP 或覆盖操作。</p>
            </div>

            <div class="install-actions">
                <a class="button button--secondary" href="{{ route('install.requirements') }}">返回</a>
                <button class="button button--primary" type="submit" data-submit-label="正在测试连接…">测试数据库并继续</button>
            </div>
        </form>
    </section>
@endsection
