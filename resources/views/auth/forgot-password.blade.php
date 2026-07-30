<x-layouts.guest title="Lupa Kata Sandi">
    <a
        href="{{ route('login') }}"
        class="mb-7 inline-flex items-center gap-2 rounded-xl text-xs font-extrabold text-slate-500 transition hover:text-sky-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-100"
    >
        <svg
            viewBox="0 0 24 24"
            class="h-4 w-4"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M19 12H5m5 5-5-5 5-5"/>
        </svg>

        Kembali ke login
    </a>

    <div class="mb-8">
        <span class="eyebrow">Pemulihan akun</span>

        <h2 class="mt-4 text-3xl font-black tracking-[-.035em] text-slate-950 sm:text-4xl">
            Lupa kata sandi?
        </h2>

        <p class="mt-3 text-sm font-medium leading-6 text-slate-500">
            Masukkan email akun aktif. Kami akan mengirim tautan aman untuk
            membuat kata sandi baru.
        </p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="form-label">
                Alamat email
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
                        <rect x="3" y="5" width="18" height="14" rx="3"/>
                        <path d="m4 7 8 6 8-6"/>
                    </svg>
                </span>

                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    required
                    autofocus
                    class="form-input form-input-with-icon @error('email') form-input-error @enderror"
                    placeholder="nama@bps.go.id"
                >
            </div>

            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="primary-button">
            <span>Kirim Tautan Reset</span>

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M5 12h14m-5-5 5 5-5 5"/>
            </svg>
        </button>
    </form>

    <div class="auth-note mt-7">
        <span class="auth-note-icon">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
                <path d="M12 8v4m0 4h.01"/>
            </svg>
        </span>

        <p>
            Demi keamanan, respons sistem tidak akan mengungkap apakah sebuah
            alamat email terdaftar atau tidak.
        </p>
    </div>
</x-layouts.guest>
