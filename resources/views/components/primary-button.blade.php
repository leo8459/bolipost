<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#fecb34] border border-[#e7b718] rounded-md font-semibold text-xs text-[#123c76] uppercase tracking-widest hover:bg-[#f4bf20] focus:bg-[#f4bf20] active:bg-[#e4af12] focus:outline-none focus:ring-2 focus:ring-[#fecb34] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
