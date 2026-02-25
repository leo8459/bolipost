@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-[#1a549a]']) }}>
    {{ $value ?? $slot }}
</label>
