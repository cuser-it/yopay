@extends('install.layout', ['title' => '安装完成'])

@section('content')
    <section class="card install-card install-card--narrow install-complete">
        <span class="install-complete-icon" aria-hidden="true">✓</span>
        <h2 class="card__title">安装完成</h2>
        <p>APP_KEY 已生成，数据库迁移和初始化已完成，首个管理员已创建。</p>
        <div class="install-alert install-alert--success">
            <strong>安装入口已经永久关闭</strong>
            <p>临时安装密钥已删除，安装会话已失效；刷新或再次访问 <code>/install</code> 将返回 404。</p>
        </div>
        <a class="button button--primary button--block" href="{{ rtrim($appUrl, '/') }}/login">进入运营台登录</a>
    </section>
@endsection
