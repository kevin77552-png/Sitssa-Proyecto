@props(['icon' => 'check'])

<div class="flex items-center gap-3 text-sm text-gray-500">
    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700">
        <span class="material-icons text-[18px]">{{ $icon }}</span>
    </span>
    <span>{{ $slot }}</span>
</div>
