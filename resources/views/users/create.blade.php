<x-layouts.app
    title="Tambah Pegawai"
    header="Tambah Pegawai"
    eyebrow="Manajemen Pengguna"
>
    <section class="mx-auto max-w-5xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">
                    Akun Baru
                </p>

                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                    Tambah Pegawai
                </h1>

                <p class="mt-2 text-sm font-medium leading-6 text-slate-500">
                    Sistem akan membuat akun berstatus menunggu aktivasi dan
                    mengirim tautan ke email pegawai.
                </p>
            </div>

            <a
                href="{{ route('users.index') }}"
                class="secondary-button sm:w-auto"
            >
                Kembali
            </a>
        </div>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">
                        Data Pegawai
                    </h2>

                    <p class="panel-subtitle">
                        Semua kolom bertanda wajib harus dilengkapi
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('users.store') }}"
                class="space-y-6 p-5 sm:p-6"
            >
                @csrf

                @include('users.partials.form')

                <div class="alert-warning">
                    <span>
                        Admin tidak membuat atau mengetahui kata sandi Pegawai.
                        Pegawai menentukan kata sandinya sendiri melalui tautan
                        aktivasi.
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
                        class="button-primary-inline sm:min-w-44"
                    >
                        Simpan & Kirim Aktivasi
                    </button>
                </div>
            </form>
        </article>
    </section>
</x-layouts.app>
