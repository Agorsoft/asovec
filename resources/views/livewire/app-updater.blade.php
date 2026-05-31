@php $errors = $errors ?? new \Illuminate\Support\ViewErrorBag; @endphp
<div class="bg-white shadow-sm rounded-lg p-6 space-y-6">
    <h3 class="text-lg font-semibold text-sky-800">Actualizador de la aplicación</h3>

    <div class="space-y-2">
        <label class="block text-sm font-medium text-slate-700">Actualizar desde URL pública</label>
        <div class="flex gap-3">
            <input type="text" wire:model.lazy="url" placeholder="https://mi-servidor/updates/app-20260530.tar.gz" class="border rounded-md px-3 py-2 w-2/3 focus:outline-none focus:ring-2 focus:ring-sky-300" />
            <input type="text" wire:model.lazy="checksum" placeholder="Checksum SHA256 (opcional)" class="border rounded-md px-3 py-2 w-1/3 focus:outline-none focus:ring-2 focus:ring-sky-300" />
            <button wire:click="startFromUrl" wire:loading.attr="disabled" wire:target="startFromUrl,startFromUpload" class="ml-2 inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md px-4 py-2 text-sm">
                <svg wire:loading wire:target="startFromUrl" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z"></path></svg>
                <span>Actualizar desde URL</span>
            </button>
        </div>
    </div>

    <div class="space-y-2">
        <label class="block text-sm font-medium text-slate-700">O suba un paquete (.zip/.tar.gz)</label>
        <div class="flex items-center gap-3">
            <input type="file" wire:model="package" class="border rounded-md px-3 py-2 text-sm" />
            <button wire:click="startFromUpload" wire:loading.attr="disabled" wire:target="startFromUpload,startFromUrl" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md px-4 py-2 text-sm">
                <svg wire:loading wire:target="startFromUpload" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z"></path></svg>
                <span>Subir y actualizar</span>
            </button>
            @error('package') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>
    </div>

    <div class="flex items-center gap-3">
        <strong class="text-sm">Estado:</strong>
        @php
            $badgeClass = match($status) {
                'running' => 'bg-yellow-100 text-yellow-800',
                'queued' => 'bg-blue-100 text-blue-800',
                'failed' => 'bg-red-100 text-red-800',
                'completed' => 'bg-emerald-100 text-emerald-800',
                default => 'bg-gray-100 text-gray-800',
            };
        @endphp
        <span class="px-2 py-1 rounded text-sm {{ $badgeClass }}">{{ $status }}</span>
    </div>

    <div wire:poll.1000ms="pollLogs">
        <div class="flex items-center justify-between">
            <strong class="text-sm">Logs:</strong>
            <span class="text-xs text-slate-500">Actualizando cada 1s</span>
        </div>
        <pre class="mt-2 bg-slate-50 border rounded-md p-4 text-xs leading-5 max-h-72 overflow-auto">@foreach($logs as $l){{ $l }}
@endforeach</pre>
    </div>
</div>
