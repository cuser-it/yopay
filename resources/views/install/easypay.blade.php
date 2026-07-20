@extends('install.layout', ['title' => 'EasyPay V2', 'step' => 3])

@section('content')
    <section class="card install-card">
        <div class="card__header">
            <h2 class="card__title">配置 EasyPay V2</h2>
            <p class="card__description">本地校验 HTTPS 地址和 RSA 密钥类型，不在页面或日志中回显私钥。</p>
        </div>

        @include('install.partials.error-summary')

        @if ($configured)
            <div class="install-alert install-alert--success">
                <strong>已有一份加密暂存配置</strong>
                <p>如需修改，请重新填写两把密钥；原密钥不会回显到浏览器。</p>
            </div>
        @endif

        <form method="post" action="{{ route('install.easypay.store') }}" class="install-form" data-install-form autocomplete="off">
            <input type="hidden" name="_install_csrf" value="{{ $csrf }}">

            <div class="install-form-grid">
                <div class="field">
                    <label class="field__label" for="merchant_id">商户 ID</label>
                    <input class="field__input" id="merchant_id" name="merchant_id" value="{{ $values['merchant_id'] ?? '' }}" required maxlength="100" autocomplete="off">
                    @include('install.partials.field-error', ['field' => 'merchant_id'])
                </div>
                <div class="field">
                    <label class="field__label" for="base_url">EasyPay V2 地址</label>
                    <input class="field__input" id="base_url" name="base_url" type="url" value="{{ $values['base_url'] ?? '' }}" required maxlength="255" placeholder="https://pay.example.com">
                    @include('install.partials.field-error', ['field' => 'base_url'])
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="merchant_private_key">商户 RSA 私钥</label>
                <textarea class="field__textarea field__textarea--secret" id="merchant_private_key" name="merchant_private_key" required maxlength="16384" rows="8" autocomplete="off" spellcheck="false" placeholder="-----BEGIN PRIVATE KEY----- 或 -----BEGIN RSA PRIVATE KEY-----"></textarea>
                <p class="field__hint">支持 PKCS#8、PKCS#1、完整 PEM 或仅 Base64 内容；请使用未设置密码的私钥。</p>
                @include('install.partials.field-error', ['field' => 'merchant_private_key'])
            </div>

            <div class="field">
                <label class="field__label" for="platform_public_key">EasyPay 平台 RSA 公钥</label>
                <textarea class="field__textarea field__textarea--secret" id="platform_public_key" name="platform_public_key" required maxlength="16384" rows="8" autocomplete="off" spellcheck="false" placeholder="-----BEGIN PUBLIC KEY-----"></textarea>
                <p class="field__hint">这里填写 EasyPay 平台公钥，不是由商户私钥自行导出的商户公钥。</p>
                @include('install.partials.field-error', ['field' => 'platform_public_key'])
                @include('install.partials.field-error', ['field' => 'easypay'])
            </div>

            <div class="install-actions">
                <a class="button button--secondary" href="{{ route('install.database') }}">返回</a>
                <button class="button button--primary" type="submit" data-submit-label="正在校验密钥…">校验并继续</button>
            </div>
        </form>
    </section>
@endsection
