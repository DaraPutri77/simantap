<x-layouts.app
    title="Edit Profil"
    header="Edit Profil"
    eyebrow="Akun"
>
    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-[0_24px_65px_rgba(15,23,42,.18)] sm:p-8">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_16%,rgba(14,165,233,.24),transparent_30%),radial-gradient(circle_at_88%_84%,rgba(37,99,235,.18),transparent_28%)]"
            aria-hidden="true"
        ></div>

        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center">
            <span class="grid h-18 w-18 shrink-0 place-items-center rounded-3xl bg-gradient-to-br from-sky-400 to-blue-700 text-2xl font-black shadow-xl shadow-blue-950/30">
                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
            </span>

            <div class="min-w-0">
                <p class="text-xs font-extrabold uppercase tracking-[.16em] text-sky-300">
                    Data pribadi
                </p>

                <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">
                    Perbarui Profil
                </h1>

                <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-300">
                    Perbarui informasi pribadi yang digunakan untuk identitas dan
                    komunikasi akun SIMANTAP.
                </p>
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">
                        Informasi yang Dapat Diedit
                    </h2>

                    <p class="panel-subtitle">
                        Nama, email, dan nomor telepon akun Anda
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('profile.update') }}"
                class="space-y-6 p-5 sm:p-6"
            >
                @csrf
                @method('PUT')

                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label
                            for="name"
                            class="form-label"
                        >
                            Nama Lengkap
                        </label>

                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $user->name) }}"
                            class="form-input @error('name') form-input-error @enderror"
                            maxlength="255"
                            autocomplete="name"
                            required
                            autofocus
                        >

                        @error('name')
                            <p class="form-error">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="email"
                            class="form-label"
                        >
                            Email
                        </label>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', $user->email) }}"
                            class="form-input @error('email') form-input-error @enderror"
                            maxlength="255"
                            autocomplete="email"
                            required
                        >

                        @error('email')
                            <p class="form-error">
                                {{ $message }}
                            </p>
                        @enderror

                        <p class="mt-2 text-xs font-medium leading-5 text-slate-400">
                            Email baru langsung digunakan untuk login dan pemulihan
                            kata sandi.
                        </p>
                    </div>

                    <div>
                        <label
                            for="phone"
                            class="form-label"
                        >
                            Nomor Telepon
                        </label>

                        <input
                            id="phone"
                            name="phone"
                            type="tel"
                            value="{{ old('phone', $user->phone) }}"
                            class="form-input @error('phone') form-input-error @enderror"
                            maxlength="30"
                            autocomplete="tel"
                            inputmode="tel"
                            placeholder="Contoh: 081234567890"
                        >

                        @error('phone')
                            <p class="form-error">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                    <a
                        href="{{ route('profile.show') }}"
                        class="secondary-button sm:w-auto sm:min-w-32"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="button-primary-inline sm:min-w-40"
                    >
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </article>

        <aside class="panel h-fit">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">
                        Data Kepegawaian
                    </h2>

                    <p class="panel-subtitle">
                        Dikelola oleh Administrator
                    </p>
                </div>
            </div>

            <dl class="space-y-5 p-5">
                @foreach ([
                    ['Nomor Pegawai / NIP', $user->employee_number],
                    ['Unit Kerja', $user->work_unit],
                    ['Jabatan', $user->position],
                    ['Status Akun', $user->status->label()],
                ] as [$label, $value])
                    <div>
                        <dt class="text-[10px] font-black uppercase tracking-[.13em] text-slate-400">
                            {{ $label }}
                        </dt>

                        <dd class="mt-2 break-words text-sm font-bold text-slate-900">
                            {{ $value ?: 'Belum diisi' }}
                        </dd>
                    </div>
                @endforeach
            </dl>

            <div class="border-t border-slate-100 p-5">
                <p class="rounded-2xl border border-sky-100 bg-sky-50 p-4 text-xs font-semibold leading-5 text-sky-800">
                    Hubungi Administrator jika NIP, unit kerja, jabatan, role,
                    atau status akun perlu diperbarui.
                </p>
            </div>
        </aside>
    </section>
</x-layouts.app>
