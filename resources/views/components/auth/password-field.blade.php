@props([
    'name',
    'label',
    'autocomplete' => 'new-password',
    'placeholder' => 'Masukkan kata sandi',
])

<div>
    <label for="{{ $name }}" class="form-label">
        {{ $label }}
    </label>

    <div class="field-control relative">
        <span class="field-icon">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <rect x="4" y="10" width="16" height="11" rx="3"/>
                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            </svg>
        </span>

        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="password"
            required
            autocomplete="{{ $autocomplete }}"
            class="form-input form-input-with-icon pr-14 {{ $errors->has($name) ? 'form-input-error' : '' }}"
            placeholder="{{ $placeholder }}"
        >

        <button
            type="button"
            class="password-toggle"
            data-target="{{ $name }}"
            data-show-label="Tampilkan {{ strtolower($label) }}"
            data-hide-label="Sembunyikan {{ strtolower($label) }}"
            aria-label="Tampilkan {{ strtolower($label) }}"
        >
            <svg
                data-password-show
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                <circle cx="12" cy="12" r="2.5"/>
            </svg>

            <svg
                data-password-hide
                viewBox="0 0 24 24"
                class="hidden"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="m3 3 18 18M10.6 6.2A10.6 10.6 0 0 1 12 6c6 0 9.5 6 9.5 6a16.7 16.7 0 0 1-2.1 2.8M6.6 6.6A16.5 16.5 0 0 0 2.5 12s3.5 6 9.5 6c1.5 0 2.8-.4 4-1"/>
            </svg>
        </button>
    </div>

    @if ($errors->has($name))
        <p class="form-error">
            {{ $errors->first($name) }}
        </p>
    @endif
</div>
