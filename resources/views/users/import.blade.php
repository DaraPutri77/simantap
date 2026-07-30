<x-layouts.app
    title="Impor Data Pegawai"
    header="Impor Data Pegawai"
    eyebrow="Manajemen Pengguna"
>
    <section class="mx-auto max-w-5xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">
                    Impor Excel / CSV
                </p>

                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                    Impor Data Pegawai
                </h1>

                <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-500">
                    Tambahkan banyak akun Pegawai sekaligus menggunakan
                    template resmi SIMANTAP.
                </p>
            </div>

            <a
                href="{{ route('users.index') }}"
                class="secondary-button sm:w-auto"
            >
                Kembali
            </a>
        </div>

        @if ($errors->any())
            <div class="alert-danger mb-6" role="alert">
                <div>
                    <p class="font-extrabold">
                        File belum dapat diimpor.
                    </p>

                    <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">
                            Unggah File Pegawai
                        </h2>

                        <p class="panel-subtitle">
                            XLSX, XLS, atau CSV · maksimal 5 MB
                        </p>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('users.import.store') }}"
                    enctype="multipart/form-data"
                    class="space-y-6 p-5 sm:p-6"
                >
                    @csrf

                    <div>
                        <label
                            for="employee_file"
                            class="form-label"
                        >
                            File Data Pegawai
                        </label>

                        <input
                            id="employee_file"
                            name="employee_file"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="form-input h-auto cursor-pointer py-4 file:mr-4 file:rounded-xl file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-xs file:font-extrabold file:text-sky-700"
                            required
                        >

                        @error('employee_file')
                            <p class="form-error">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="alert-warning">
                        <span>
                            Impor hanya diproses jika seluruh baris valid.
                            NIP dan email tidak boleh berulang di file maupun
                            database.
                        </span>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                        <a
                            href="{{ route('users.index') }}"
                            class="secondary-button sm:w-auto sm:min-w-32"
                        >
                            Batal
                        </a>

                        <button
                            type="submit"
                            class="button-primary-inline sm:min-w-40"
                            data-submit-label="Mengimpor..."
                        >
                            Impor Pegawai
                        </button>
                    </div>
                </form>
            </article>

            <aside class="space-y-6">
                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">
                                1. Unduh Template
                            </h2>

                            <p class="panel-subtitle">
                                Gunakan susunan kolom yang benar
                            </p>
                        </div>
                    </div>

                    <div class="p-5">
                        <a
                            href="{{ route('users.import.template') }}"
                            class="button-primary-inline w-full"
                        >
                            Unduh Template Excel
                        </a>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">
                                2. Isi Data
                            </h2>

                            <p class="panel-subtitle">
                                Jangan mengubah nama kolom
                            </p>
                        </div>
                    </div>

                    <ol class="space-y-3 p-5 text-xs font-semibold leading-5 text-slate-600">
                        <li>
                            <strong class="text-slate-900">nip</strong> —
                            formatkan sebagai teks agar nol di depan tidak hilang.
                        </li>
                        <li>
                            <strong class="text-slate-900">nama_lengkap</strong>
                            dan <strong class="text-slate-900">email</strong>
                            wajib diisi.
                        </li>
                        <li>
                            <strong class="text-slate-900">nomor_telepon</strong>
                            boleh dikosongkan.
                        </li>
                        <li>
                            <strong class="text-slate-900">unit_kerja</strong>
                            dan <strong class="text-slate-900">jabatan</strong>
                            wajib diisi.
                        </li>
                    </ol>
                </article>

                <p class="rounded-2xl border border-sky-100 bg-sky-50 p-4 text-xs font-semibold leading-5 text-sky-800">
                    Setelah impor berhasil, setiap Pegawai menerima tautan
                    aktivasi melalui email dan menentukan kata sandinya sendiri.
                </p>
            </aside>
        </div>
    </section>
</x-layouts.app>
