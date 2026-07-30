<x-layouts.app
    title="Edit Pegawai"
    header="Edit Pegawai"
    eyebrow="Manajemen Pengguna"
>
    <section class="mx-auto max-w-5xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">
                    Data Kepegawaian
                </p>

                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                    Edit Pegawai
                </h1>

                <p class="mt-2 text-sm font-medium leading-6 text-slate-500">
                    Perbarui identitas dan data kerja
                    {{ $employee->name }}.
                </p>
            </div>

            <a
                href="{{ route('users.show', $employee) }}"
                class="secondary-button sm:w-auto"
            >
                Kembali ke Detail
            </a>
        </div>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">
                        Informasi Pegawai
                    </h2>

                    <p class="panel-subtitle">
                        Role dan status akun dikelola melalui tindakan terpisah
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('users.update', $employee) }}"
                class="space-y-6 p-5 sm:p-6"
            >
                @csrf
                @method('PUT')

                @include('users.partials.form', [
                    'employee' => $employee,
                ])

                <div class="alert-warning">
                    <span>
                        Perubahan email akan mengubah alamat login, aktivasi,
                        dan pemulihan kata sandi pegawai.
                    </span>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                    <a
                        href="{{ route('users.show', $employee) }}"
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
    </section>
</x-layouts.app>
