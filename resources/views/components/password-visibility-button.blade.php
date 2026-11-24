@props(['target', 'description'])

<button type="button"
    class="absolute inset-y-px right-px flex w-11 items-center justify-center rounded-r-md text-[#20539A] hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-[#20539A]"
    aria-controls="{{ $target }}"
    aria-label="Mostrar {{ $description }}"
    data-password-toggle
    data-show-label="Mostrar {{ $description }}"
    data-hide-label="Ocultar {{ $description }}">
    <svg data-eye-open class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
        <circle cx="12" cy="12" r="3" />
    </svg>
    <svg data-eye-closed class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8" />
        <path d="M9.9 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a11.4 11.4 0 0 1-4.1 5.2M6.1 6.1A11.5 11.5 0 0 0 2 12s3.5 7 10 7a10.8 10.8 0 0 0 4.9-1.2" />
    </svg>
</button>
