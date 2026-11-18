<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-lg border border-transparent bg-brand-900 px-4 py-2 font-semibold text-xs uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-900 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
