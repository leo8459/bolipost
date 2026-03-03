<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#FECC36] border border-[#FECC36] rounded-md font-semibold text-xs text-[#20539A] uppercase tracking-widest hover:bg-[#FECC36] focus:bg-[#FECC36] active:bg-[#FECC36] focus:outline-none focus:ring-2 focus:ring-[#FECC36] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>

