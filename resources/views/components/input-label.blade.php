@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-[#20539A]']) }}>
    {{ $value ?? $slot }}
</label>

