@props(['tone' => 'info'])

<span {{ $attributes->class(['badge', 'badge--'.$tone]) }}>{{ $slot }}</span>
