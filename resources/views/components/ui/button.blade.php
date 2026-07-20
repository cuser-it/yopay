@props(['type' => 'button', 'variant' => 'primary', 'block' => false])

<button
    type="{{ $type }}"
    {{ $attributes->class(['button', 'button--'.$variant, 'button--block' => $block]) }}
>
    {{ $slot }}
</button>
