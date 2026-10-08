@props(['photo' => null, 'label' => 'Photo', 'hint' => 'JPG or PNG up to 5 MB. Compressed automatically when saved.', 'required' => false])
{{-- Binds $photo (Livewire WithFileUploads); the component saves it through PhotoCompressionService. --}}
<x-ui.field :label="$label" error="photo" :optional="! $required">
    @if ($photo && ! $errors->has('photo'))
        <div class="flex items-center gap-3.5 p-3 rounded-xl ring-1 ring-inset ring-line-light">
            <img src="{{ $photo->temporaryUrl() }}" alt="" class="w-16 h-16 rounded-lg object-cover ring-1 ring-line">
            <div class="flex-1 min-w-0">
                <div class="text-[13px] font-semibold text-ink_text-primary truncate">{{ $photo->getClientOriginalName() }}</div>
                <div class="text-[12px] text-ink_text-muted">{{ number_format($photo->getSize() / 1024, 0) }} KB before compression</div>
            </div>
            <x-ui.button variant="ghost" size="sm" icon="x" wire:click="removePhoto">Remove</x-ui.button>
        </div>
    @else
        <label class="relative flex items-center gap-3.5 px-4 py-4 rounded-xl border-2 border-dashed border-line hover:border-gold hover:bg-gold-tint/40 cursor-pointer transition-colors"
               x-data="{ drag: false }" x-on:dragover.prevent="drag = true" x-on:dragleave="drag = false" x-on:drop="drag = false"
               :class="drag ? 'border-gold bg-gold-tint/60' : ''">
            <input type="file" wire:model="photo" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
            <span class="w-10 h-10 shrink-0 rounded-xl bg-white ring-1 ring-line shadow-card text-gold-dark flex items-center justify-center">
                <x-ui.icon name="camera" :size="18" wire:loading.remove wire:target="photo" />
                <x-ui.icon name="loader" :size="18" class="animate-spin" wire:loading wire:target="photo" />
            </span>
            <span>
                <span class="block text-[13px] font-semibold text-ink_text-primary">Drop a photo or click to choose</span>
                <span class="block text-[12px] text-ink_text-muted">{{ $hint }}</span>
            </span>
        </label>
    @endif
</x-ui.field>
