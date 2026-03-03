@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-[#20539A]']) }}>
        {{ $status }}
    </div>
@endif

