{{-- Website tab of the Add/Edit Item form (staff with website.manage only). --}}
@php
    $chip = 'relative inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full text-[13px] font-semibold ring-1 ring-inset cursor-pointer transition-colors select-none';
@endphp
<div class="space-y-7 min-w-0">
    {{-- On / off --}}
    <section>
        <label @class([
            'flex items-start gap-3.5 p-4 rounded-card border cursor-pointer transition-colors',
            'border-gold bg-gold-tint shadow-focus' => $show_on_website,
            'border-line hover:border-line-strong' => ! $show_on_website,
        ])>
            <input type="checkbox" wire:model.live="show_on_website" class="rj-checkbox mt-0.5">
            <span class="min-w-0">
                <span class="block text-[14px] font-bold text-ink_text-primary">Show this piece on the website</span>
                <span class="block text-[12.5px] text-ink_text-secondary mt-0.5">
                    It appears only while it's in stock. Once it's reserved in a sale, sent out, or sold, it comes off the site on its own.
                </span>
            </span>
        </label>

        @if ($show_on_website && $category && ! $webCategory)
            <div class="flex items-start gap-2.5 mt-3 px-3.5 py-2.5 rounded-control bg-warning-bg text-[12.5px] text-ink_text-primary">
                <x-ui.icon name="alert-triangle" :size="14" class="shrink-0 mt-0.5 text-warning" />
                <span>“{{ $category }}” is switched off in Stock › Categories, so this piece won't show.</span>
            </div>
        @elseif ($webCategory)
            <p class="mt-2.5 text-[12.5px] text-ink_text-secondary flex items-center gap-1.5">
                <x-ui.icon name="check-circle" :size="13" class="text-success" /> Listed under <span class="font-semibold text-ink_text-primary">{{ $webCategory->name }}</span> on the website.
            </p>
        @endif
    </section>

    {{-- Name --}}
    <section class="pt-6 border-t border-line-light">
        <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">Name and description</h3>
        <div class="space-y-4">
            <x-ui.field label="Website name" for="w-name" error="web_name" :optional="! $show_on_website"
                :hint="$slug ? 'Web address: /shop/'.$slug : 'Its web address is made from this name the first time it goes on the site.'">
                <input id="w-name" type="text" wire:model.live.debounce.500ms="web_name" maxlength="120" class="rj-input @error('web_name') is-invalid @enderror" placeholder="e.g. Meenakari Jhumka" autocomplete="off">
            </x-ui.field>
            <x-ui.field label="Description" for="w-desc" error="web_description" optional hint="A sentence or two a customer would want to read.">
                <textarea id="w-desc" rows="3" wire:model="web_description" maxlength="2000" class="rj-textarea"
                    placeholder="e.g. A bell jhumka with a hand-painted meenakari dome and a fringe of fine gold beads."></textarea>
            </x-ui.field>
        </div>
    </section>

    {{-- Photos --}}
    <section class="pt-6 border-t border-line-light">
        <div class="flex items-baseline justify-between gap-3 mb-3.5">
            <h3 class="text-[13px] font-bold text-ink_text-primary">Photos</h3>
            <span class="text-[12px] text-ink_text-muted">The first photo is the one on the product card. Up to {{ \App\Livewire\Stock\ItemForm::MAX_PHOTOS }}.</span>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
            @foreach ($photos as $i => $p)
                <div wire:key="photo-{{ $p['id'] }}" class="group relative aspect-square rounded-control overflow-hidden ring-1 ring-line bg-surface-sunken">
                    <img src="{{ \App\Support\StorefrontImage::sized($p['url'], 300, 300) }}" alt="" class="w-full h-full object-cover">
                    @if ($i === 0)<span class="absolute left-1.5 top-1.5"><x-ui.badge tone="dark" size="sm">Cover</x-ui.badge></span>@endif
                    <div class="absolute inset-x-1.5 bottom-1.5 flex justify-between gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                        <div class="flex gap-1">
                            @if ($i > 0)
                                <button type="button" wire:click="movePhoto({{ $i }}, -1)" class="w-7 h-7 rounded-lg bg-white/95 shadow text-ink_text-primary flex items-center justify-center" title="Move earlier" aria-label="Move photo earlier"><x-ui.icon name="chevron-left" :size="14" /></button>
                            @endif
                            @if ($i < count($photos) - 1)
                                <button type="button" wire:click="movePhoto({{ $i }}, 1)" class="w-7 h-7 rounded-lg bg-white/95 shadow text-ink_text-primary flex items-center justify-center" title="Move later" aria-label="Move photo later"><x-ui.icon name="chevron-right" :size="14" /></button>
                            @endif
                        </div>
                        <button type="button" wire:click="removePhoto({{ $i }})" class="w-7 h-7 rounded-lg bg-white/95 shadow text-danger flex items-center justify-center" title="Remove photo" aria-label="Remove photo"><x-ui.icon name="trash" :size="14" /></button>
                    </div>
                </div>
            @endforeach

            @foreach ($newPhotos as $i => $upload)
                <div wire:key="new-photo-{{ $i }}" class="group relative aspect-square rounded-control overflow-hidden ring-1 ring-gold bg-surface-sunken">
                    @if (method_exists($upload, 'isPreviewable') && $upload->isPreviewable())
                        <img src="{{ $upload->temporaryUrl() }}" alt="" class="w-full h-full object-cover">
                    @endif
                    <span class="absolute left-1.5 top-1.5"><x-ui.badge tone="gold" size="sm">New</x-ui.badge></span>
                    <button type="button" wire:click="removeNewPhoto({{ $i }})" class="absolute right-1.5 bottom-1.5 w-7 h-7 rounded-lg bg-white/95 shadow text-danger flex items-center justify-center" title="Remove photo" aria-label="Remove photo"><x-ui.icon name="trash" :size="14" /></button>
                </div>
            @endforeach

            @if (count($photos) + count($newPhotos) < \App\Livewire\Stock\ItemForm::MAX_PHOTOS)
                <label class="relative aspect-square rounded-control border-2 border-dashed border-line-strong hover:border-gold hover:bg-gold-tint/40 transition-colors flex flex-col items-center justify-center gap-1.5 text-center cursor-pointer px-2">
                    <x-ui.icon name="camera" :size="20" class="text-ink_text-secondary" wire:loading.remove wire:target="newPhotos" />
                    <x-ui.icon name="loader" :size="20" class="animate-spin text-gold" wire:loading wire:target="newPhotos" />
                    <span class="text-[12px] font-semibold text-ink_text-secondary" wire:loading.remove wire:target="newPhotos">Add photos</span>
                    <span class="text-[12px] font-semibold text-gold-dark" wire:loading wire:target="newPhotos">Uploading...</span>
                    <input type="file" wire:model="newPhotos" accept="image/*" multiple class="absolute inset-0 opacity-0 cursor-pointer">
                </label>
            @endif
        </div>
        @error('newPhotos') <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
        @error('newPhotos.*') <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
        @if ($newPhotos)
            <p class="rj-help">New photos are compressed and saved when you save the piece.</p>
        @endif
    </section>

    {{-- Where it shows --}}
    <section class="pt-6 border-t border-line-light">
        <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">Where it shows</h3>
        <div class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                <x-ui.field label="Collection" for="w-collection" error="storefront_collection_id" optional>
                    <select id="w-collection" wire:model="storefront_collection_id" class="rj-select">
                        <option value="">Not in a collection</option>
                        @foreach ($collections as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}{{ $c->is_active ? '' : ' (hidden)' }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <label class="inline-flex items-center gap-2.5 text-[13.5px] font-semibold text-ink_text-primary cursor-pointer pb-2.5">
                    <input type="checkbox" wire:model="is_bestseller" class="rj-checkbox"> Mark as a bestseller
                </label>
            </div>

            <div>
                <span class="rj-label">Who it's for</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (config('storefront.audiences') as $k => $v)
                        <label @class([$chip, 'bg-ink text-gold-light ring-ink' => in_array($k, $audiences), 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' => ! in_array($k, $audiences)])>
                            <input type="checkbox" wire:model.live="audiences" value="{{ $k }}" class="sr-only">{{ $v }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <span class="rj-label">Occasions</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (config('storefront.occasions') as $k => $v)
                        <label @class([$chip, 'bg-ink text-gold-light ring-ink' => in_array($k, $occasions), 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' => ! in_array($k, $occasions)])>
                            <input type="checkbox" wire:model.live="occasions" value="{{ $k }}" class="sr-only">{{ $v }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Size --}}
    <section class="pt-6 border-t border-line-light">
        <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">Size</h3>
        <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_160px_120px] gap-4">
            <x-ui.field label="Dimensions" for="w-dims" error="dimensions" optional hint="e.g. Drop length 4.2 cm">
                <input id="w-dims" type="text" wire:model="dimensions" maxlength="120" class="rj-input">
            </x-ui.field>
            <x-ui.field label="Size kind" for="w-size-type" error="size_type" optional>
                <select id="w-size-type" wire:model.live="size_type" class="rj-select">
                    <option value="">No size</option>
                    @foreach (config('storefront.size_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Size" for="w-size" error="size_label" optional>
                <input id="w-size" type="text" wire:model="size_label" maxlength="20" class="rj-input tabular" @disabled(! $size_type)
                    placeholder="{{ ['ring' => '12', 'bangle' => '2.4', 'chain' => '18 in'][$size_type] ?? '' }}">
            </x-ui.field>
        </div>
    </section>
</div>
