@props(['category' => null, 'size' => 'h-9 w-9', 'iconSize' => 'h-[18px] w-[18px]', 'rounded' => 'rounded-xl'])
@php [$ic, $tint] = \App\Support\CategoryIcon::for($category); @endphp
<span {{ $attributes->merge(['class' => "grid shrink-0 place-items-center $size $rounded $tint"]) }} title="{{ $category?->name }}"><x-icon :name="$ic" :class="$iconSize"/></span>
