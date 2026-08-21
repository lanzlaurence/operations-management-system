@props([
    'name',
    'variant' => 'o',
])

{{--
    Heroicon by name: <x-icon name="cube" /> renders heroicon-o-cube.
    An unknown name falls back to a neutral glyph instead of throwing, so a
    typo in config/navigation.php can never break the whole shell.
--}}
@php
    $icon = "heroicon-{$variant}-{$name}";

    try {
        app(\BladeUI\Icons\Factory::class)->svg($icon);
    } catch (\Throwable) {
        $icon = 'heroicon-o-square-3-stack-3d';
    }
@endphp

@svg($icon, $attributes->get('class', 'size-5'), $attributes->except('class')->getAttributes())
