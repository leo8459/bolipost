@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-[#FECC36] focus:border-[#FECC36] focus:ring-[#FECC36]/35 rounded-md shadow-sm']) }}>

