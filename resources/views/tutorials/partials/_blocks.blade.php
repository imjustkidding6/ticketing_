{{--
    Renders exactly what HelpService returned, and re-checks nothing.

    Every permission decision was already made in the service. A second check
    here is how the page and the PDF start disagreeing about what a role can see.
--}}
@foreach($blocks as $block)
    @if(isset($block['h']))
        <h4 class="mt-6 mb-2 text-base font-bold text-[var(--text-primary)]">{{ $block['h'] }}</h4>

    @elseif(isset($block['p']))
        <p class="mb-3 text-sm leading-relaxed text-[var(--text-secondary)]">{!! $block['p'] !!}</p>

    @elseif(isset($block['steps']))
        <ol class="mb-4 space-y-2">
            @foreach($block['steps'] as $i => $step)
                <li class="flex gap-3 text-sm leading-relaxed text-[var(--text-secondary)]">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#EEF0FF] text-xs font-bold text-[#4B4FD6]">{{ $i + 1 }}</span>
                    <span>{!! $step !!}</span>
                </li>
            @endforeach
        </ol>

    @elseif(isset($block['ul']))
        <ul class="mb-4 ml-1 space-y-1.5">
            @foreach($block['ul'] as $line)
                <li class="flex gap-2 text-sm leading-relaxed text-[var(--text-secondary)]">
                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-[#5B5FF6]"></span>
                    <span>{!! $line !!}</span>
                </li>
            @endforeach
        </ul>

    @elseif(isset($block['tip']) || isset($block['note']) || isset($block['warn']))
        @php
            $kind = isset($block['tip']) ? 'tip' : (isset($block['note']) ? 'note' : 'warn');
            $style = [
                'tip'  => ['border' => '#16A34A', 'bg' => '#F0FDF4', 'label' => __('Tip')],
                'note' => ['border' => '#5B5FF6', 'bg' => '#EEF0FF', 'label' => __('Note')],
                'warn' => ['border' => '#D97706', 'bg' => '#FFFBEB', 'label' => __('Watch out')],
            ][$kind];
        @endphp
        <div class="mb-4 rounded-lg border-l-4 p-3.5" style="border-color: {{ $style['border'] }}; background: {{ $style['bg'] }};">
            <p class="mb-1 text-xs font-bold uppercase tracking-wide" style="color: {{ $style['border'] }};">{{ $style['label'] }}</p>
            <p class="text-sm leading-relaxed text-gray-700">{!! $block[$kind] !!}</p>
        </div>

    @elseif(isset($block['faq']))
        <div class="mb-4 divide-y divide-[var(--border-soft)] rounded-lg border border-[var(--border-soft)]">
            @foreach($block['faq'] as $entry)
                <details class="group p-3.5" data-help-faq>
                    <summary class="cursor-pointer list-none text-sm font-semibold text-[var(--text-primary)]">
                        {!! $entry['q'] !!}
                    </summary>
                    <p class="mt-2 text-sm leading-relaxed text-[var(--text-secondary)]">{!! $entry['a'] !!}</p>
                </details>
            @endforeach
        </div>
    @endif
@endforeach
