@props([
    'label',
    'value',
    'caption',
    'icon' => 'chart',
])

<section class="summary-card">
    <div>
        <p class="summary-label">{{ $label }}</p>
        <p class="summary-value">{{ number_format($value, 0, ',', ' ') }} Ft</p>
        <p class="summary-caption">{{ $caption }}</p>
    </div>

    <div class="summary-icon-shell">
        @if ($icon === 'money')
            <svg viewBox="0 0 24 24" fill="none" class="summary-icon">
                <path d="M12 3V21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M16.5 7.5C16.5 5.843 14.485 4.5 12 4.5C9.515 4.5 7.5 5.843 7.5 7.5C7.5 9.157 9.515 10.5 12 10.5C14.485 10.5 16.5 11.843 16.5 13.5C16.5 15.157 14.485 16.5 12 16.5C9.515 16.5 7.5 15.157 7.5 13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
            </svg>
        @else
            <svg viewBox="0 0 24 24" fill="none" class="summary-icon">
                <path d="M6 18.5H18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M8 18V11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M12 18V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M16 18V4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        @endif
    </div>
</section>
