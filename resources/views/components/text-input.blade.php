@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-[#dcb15f] focus:border-[#fecb34] focus:ring-[#fecb34]/35 rounded-md shadow-sm']) }}>
