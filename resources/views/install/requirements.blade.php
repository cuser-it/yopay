@extends('install.layout', ['title' => '环境检查', 'step' => 1])

@section('content')
    <section class="card install-card">
        <div class="card__header install-section-heading">
            <div>
                <h2 class="card__title">服务器环境检查</h2>
                <p class="card__description">所有项目通过后才能写入配置或连接数据库。</p>
            </div>
            <span class="badge {{ $passes ? 'badge--success' : 'badge--error' }}">{{ $passes ? '全部通过' : '需要处理' }}</span>
        </div>

        <ul class="install-checks">
            @foreach ($checks as $check)
                <li>
                    <span class="install-check-icon {{ $check['passed'] ? 'install-check-icon--success' : 'install-check-icon--error' }}" aria-hidden="true">{{ $check['passed'] ? '✓' : '!' }}</span>
                    <div>
                        <strong>{{ $check['label'] }}</strong>
                        <span>{{ $check['detail'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="install-actions">
            <a class="button button--secondary" href="{{ route('install.requirements') }}">重新检查</a>
            @if ($passes)
                <a class="button button--primary" href="{{ route('install.database') }}">填写站点与数据库</a>
            @endif
        </div>
    </section>
@endsection
