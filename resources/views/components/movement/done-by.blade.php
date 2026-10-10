@props(['label' => 'Done by'])
{{-- Binds $doneBy (HasDoneBy). The logged-in user is still recorded as the actor. --}}
<x-ui.field :label="$label" for="done-by" error="doneBy" hint="Who physically did it. You are still recorded as the person logged in.">
    <select id="done-by" wire:model="doneBy" class="rj-select">
        <option value="">Myself</option>
        @foreach (\App\Models\Employee::where('status', 'active')->orderBy('name')->get(['id', 'name']) as $emp)
            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
        @endforeach
    </select>
</x-ui.field>
