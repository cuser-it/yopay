@if (! empty($formErrors ?? []))
    <div class="install-alert install-alert--error" role="alert">
        <strong>当前步骤未完成</strong>
        <ul>
            @foreach ($formErrors as $messages)
                @foreach ($messages as $message)
                    <li>{{ $message }}</li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
