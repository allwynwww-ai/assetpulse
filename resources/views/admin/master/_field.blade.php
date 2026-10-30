@php
    $name = $field['name'];
    $type = $field['type'] ?? 'text';
    $value = old($name, $field['value'] ?? data_get($record, $name));
    if ($value instanceof \Carbon\CarbonInterface) { $value = $value->format('Y-m-d'); }
    if ($value instanceof \BackedEnum) { $value = $value->value; }
    $disabled = ($readonly ?? false) || ($field['disabled'] ?? false);
    $span = match ($field['span'] ?? 1) { 'full' => 'md:col-span-2 lg:col-span-3', 2 => 'md:col-span-2', default => '' };
    $id = 'f_'.$name;
@endphp
<div class="{{ $span }}">
    @if ($type === 'checkbox')
        <label class="mt-6 flex items-start gap-2.5">
            <input type="hidden" name="{{ $name }}" value="0">
            <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" class="form-check mt-0.5" @checked($value) @disabled($disabled)>
            <span>
                <span class="text-sm font-medium text-slate-700">{{ $field['label'] }}</span>
                @if (! empty($field['help']))<span class="block text-xs text-slate-400">{{ $field['help'] }}</span>@endif
            </span>
        </label>
    @else
        <label for="{{ $id }}" class="form-label">{{ $field['label'] }} @if (! empty($field['required']) && ! $disabled)<span class="text-rose-500">*</span>@endif</label>
        @if ($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $field['rows'] ?? 3 }}" class="form-input" placeholder="{{ $field['placeholder'] ?? '' }}" @disabled($disabled)>{{ $value }}</textarea>
        @elseif ($type === 'select')
            <select id="{{ $id }}" name="{{ $name }}" class="form-input" @disabled($disabled)>
                @if (! array_key_exists('placeholder', $field) || $field['placeholder'] !== false)
                    <option value="">{{ $field['placeholder'] ?? '— Select —' }}</option>
                @endif
                @foreach ($field['options'] as $val => $lbl)
                    <option value="{{ $val }}" @selected((string) $value === (string) $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        @else
            <div class="relative">
                @if (! empty($field['prefix']))
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">{{ $field['prefix'] }}</span>
                @endif
                <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $value }}"
                       class="form-input {{ ! empty($field['prefix']) ? 'pl-7' : '' }} {{ ! empty($field['suffix']) ? 'pr-12' : '' }} {{ in_array($type, ['number']) ? 'tabular-nums' : '' }}"
                       placeholder="{{ $field['placeholder'] ?? '' }}"
                       @if (isset($field['step'])) step="{{ $field['step'] }}" @endif
                       @if (isset($field['min'])) min="{{ $field['min'] }}" @endif
                       @if (isset($field['max'])) max="{{ $field['max'] }}" @endif
                       @if (isset($field['maxlength'])) maxlength="{{ $field['maxlength'] }}" @endif
                       @if ($type === 'password') autocomplete="new-password" @endif
                       @disabled($disabled)>
                @if (! empty($field['suffix']))
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $field['suffix'] }}</span>
                @endif
            </div>
        @endif
        @if (! empty($field['help']))<p class="form-help">{{ $field['help'] }}</p>@endif
    @endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</div>
