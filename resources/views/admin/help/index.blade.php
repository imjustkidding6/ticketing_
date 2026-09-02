@extends('layouts.admin')

@section('title', 'Help & Tutorials')

@section('content')
<style>
    :root {
        --bg-app: #F7F8FC; --bg-card: #FFFFFF; --bg-hover: #F1F2F9;
        --text-primary: #1A1D2E; --text-secondary: #5A6079; --border-soft: #E6E8F0;
        --shadow-soft: 0 1px 2px rgba(26,29,46,.05), 0 8px 24px -18px rgba(26,29,46,.35);
    }
    [data-help-topic] { scroll-margin-top: 96px; }
</style>

<div class="py-6">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="mb-6 rounded-2xl bg-gradient-to-r from-[#4F46E5] to-[#6D28D9] p-8 text-white shadow-lg">
            <h1 class="text-2xl font-extrabold sm:text-3xl">{{ __('Help & Tutorials') }}</h1>
            <p class="mt-1 text-sm text-indigo-100">
                {{ __('The complete guide — every topic in the product, unscoped.') }}
                {{ __('About') }} <strong>{{ array_sum(array_column($topics, 'minutes')) }} {{ __('minutes') }}</strong>
                {{ __('of reading across') }} <strong>{{ count($topics) }} {{ __('topics') }}</strong>.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.help.download-manual') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-white/95 px-4 py-2.5 text-sm font-semibold text-[#4338CA] shadow-sm hover:bg-white">
                    {{ __('Download the system manual (PDF)') }}
                </a>
            </div>
        </div>

        {{-- Platform staff hold no tenant role, so this is the whole guide.
             A tenant reading the same content at /{slug}/tutorials sees only
             the parts their role can act on. --}}
        <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-sm text-indigo-900">
                <strong>{{ __('This is the unscoped guide.') }}</strong>
                {{ __('Inside a workspace, each person sees only the topics their role can reach — so what a customer describes may be a subset of what is below.') }}
            </p>
        </div>

        <div class="flex flex-col gap-8 lg:flex-row">
            <aside class="lg:w-[24%] shrink-0">
                <div class="sticky top-24 rounded-2xl border border-[var(--border-soft)] bg-white p-4 shadow-[var(--shadow-soft)]">
                    <p class="px-2 pb-2 text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">{{ __('Contents') }}</p>
                    @foreach($groups as $group)
                        <div class="mb-3">
                            <p class="px-2 pb-1 text-[11px] font-bold uppercase tracking-wider text-[var(--text-secondary)]">{{ $group['label'] }}</p>
                            @foreach($group['topics'] as $key => $topic)
                                <a href="#topic-{{ $key }}"
                                   class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm font-medium text-[var(--text-primary)] hover:bg-[var(--bg-hover)]">
                                    <span>{{ $topic['title'] }}</span>
                                    <span class="shrink-0 text-[11px] text-[var(--text-secondary)]">{{ $topic['minutes'] }}m</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </aside>

            <div class="flex-1 space-y-6">
                @foreach($topics as $key => $topic)
                    <article id="topic-{{ $key }}" data-help-topic="{{ $key }}"
                             class="rounded-2xl border border-[var(--border-soft)] bg-white p-6 shadow-[var(--shadow-soft)] sm:p-8">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-[var(--border-soft)] pb-4">
                            <div>
                                <h2 class="text-xl font-extrabold text-[var(--text-primary)]">{{ $topic['title'] }}</h2>
                                <p class="mt-1 text-sm text-[var(--text-secondary)]">{{ $topic['summary'] ?? '' }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1 rounded-full bg-[#EEF0FF] px-2.5 py-1 text-xs font-semibold text-[#4B4FD6]">
                                    ~{{ $topic['minutes'] }} {{ __('min') }}
                                </span>
                                @if(! empty($topic['permission']))
                                    {{-- Naming the permission is the point: it is what a
                                         customer will be told to ask for. --}}
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-mono text-[11px] text-gray-600">
                                        {{ implode(' / ', (array) $topic['permission']) }}
                                    </span>
                                @endif
                                @if(! empty($topic['feature']))
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 font-mono text-[11px] text-amber-800">
                                        {{ $topic['feature'] }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @include('tutorials.partials._blocks', ['blocks' => $topic['blocks']])
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
