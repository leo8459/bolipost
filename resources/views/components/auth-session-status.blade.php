@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-[#1a549a]']) }}>
        {{ $status }}
    </div>
@endif
