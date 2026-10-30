<x-app-layout title="Reports">
    <x-slot name="header">
        <x-page-header title="Reports" subtitle="Every report can be filtered and exported to Excel or PDF (A4 landscape)." icon="chart" :crumbs="['Reports' => null]"/>
    </x-slot>
    <div class="space-y-8">
        @foreach ($groups as $group => $reports)
            <section>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $group }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $rep)
                        <div class="card flex flex-col p-5 transition hover:shadow-soft">
                            <a href="{{ route('reports.show', $rep['slug']) }}" class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$rep['icon']" class="h-5 w-5"/></div>
                                <div><div class="font-semibold text-slate-800">{{ $rep['title'] }}</div><p class="mt-1 text-sm text-slate-500">{{ $rep['desc'] }}</p></div>
                            </a>
                            <div class="mt-4 flex gap-2 border-t border-slate-100 pt-3">
                                <a href="{{ route('reports.show', $rep['slug']) }}" class="btn-ghost btn-sm"><x-icon name="eye" class="h-3.5 w-3.5"/> View</a>
                                <a href="{{ route('reports.show', [$rep['slug'], 'export' => 'xlsx']) }}" class="btn-ghost btn-sm"><x-icon name="download" class="h-3.5 w-3.5"/> Excel</a>
                                <a href="{{ route('reports.show', [$rep['slug'], 'export' => 'pdf']) }}" class="btn-ghost btn-sm"><x-icon name="printer" class="h-3.5 w-3.5"/> PDF</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
