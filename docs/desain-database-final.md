# Desain Database Final SIMANTAP

## 1. Ruang Lingkup

Dokumen ini menjadi sumber migration SIMANTAP untuk akun internal, role dan
permission, persediaan, permintaan dan approval barang, kendaraan dinas,
peminjaman dan pengembalian, pemeliharaan, bukti digital, notifikasi, nomor
dokumen, pengaturan, dan audit log.

Sistem melayani 39 pengguna internal: 1 admin dan 38 pegawai. Registrasi publik
tidak disediakan.

## 2. Keputusan Arsitektur

1. Profil pegawai dan akun login disimpan pada `users`. Tabel `employees` tidak
   dibuat karena seluruh pegawai adalah pengguna dan relasinya selalu 1:1.
2. Role dan permission hanya menggunakan Spatie Permission. Tidak ada kolom
   `role` atau `role_id` pada `users`.
3. Akun diaktifkan melalui tautan sekali pakai. Password boleh `NULL` sebelum
   aktivasi dan admin tidak mengetahui password pegawai.
4. Status disimpan sebagai `varchar` dan dikendalikan PHP backed enum.
5. Waktu disimpan dalam UTC dan ditampilkan dalam `Asia/Jakarta`.
6. `items.current_stock` dan `items.reserved_stock` menjadi saldo cepat.
   `stock_movements` menjadi ledger immutable dan sumber kartu kendali.
7. Perubahan stok memakai database transaction dan row locking.
8. Barang masuk, penyesuaian, dan permintaan memakai pola header-detail.
9. Lampiran dan tanda tangan memakai relasi polymorphic.
10. Status history, stock movement, dan audit log bersifat append-only.
11. Transaksi memakai soft delete. Ledger dan audit tidak dapat dihapus.
12. Filter tahun berasal dari tanggal transaksi, bukan kolom tahun tambahan.

## 3. Konvensi

- Primary/foreign key: `BIGINT UNSIGNED`.
- Kuantitas: `DECIMAL(15,2)`.
- Uang: `DECIMAL(19,2)`.
- Nomor dokumen: `VARCHAR(80)` unique.
- Status: `VARCHAR(40)` dan index.
- IP address: `VARCHAR(45)`.
- Hash SHA-256: `CHAR(64)`.
- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`.

## 4. Tabel Infrastruktur

Laravel menggunakan `password_reset_tokens`, `sessions`, `cache`,
`cache_locks`, `jobs`, `job_batches`, dan `failed_jobs`.

Spatie Permission menggunakan `roles`, `permissions`, `model_has_roles`,
`model_has_permissions`, dan `role_has_permissions`.

## 5. Akun

### `users`

Kolom:

`id`; `employee_number varchar(50) nullable unique`; `name varchar(255)`;
`email varchar(255) unique`; `phone varchar(30) nullable`;
`work_unit varchar(255) nullable`; `position varchar(255) nullable`;
`status varchar(30) index`; `password varchar(255) nullable`;
`must_change_password boolean default false`;
`email_verified_at timestamp nullable`; `activated_at timestamp nullable`;
`password_changed_at timestamp nullable`; `last_login_at timestamp nullable`;
`created_by foreignId nullable nullOnDelete`; `remember_token`;
`created_at`; `updated_at`; `deleted_at`.

Status: `pending_activation`, `active`, `inactive`, `suspended`.

### `account_activation_tokens`

Kolom:

`id`; `user_id foreignId unique cascadeOnDelete`; `token_hash char(64) unique`;
`expires_at timestamp`; `used_at timestamp nullable`;
`created_by foreignId nullable nullOnDelete`; `created_at`; `updated_at`.

## 6. Master Persediaan

### `item_categories`

Kolom:

`id`; `name varchar(255) unique`; `description text nullable`;
`is_active boolean default true index`; `created_at`; `updated_at`;
`deleted_at`.

### `units`

Kolom:

`id`; `name varchar(255) unique`; `symbol varchar(30) unique`;
`is_active boolean default true index`; `created_at`; `updated_at`;
`deleted_at`.

### `items`

Kolom:

`id`; `public_id uuid unique`; `item_code varchar(80) unique`;
`category_id foreignId restrictOnDelete`; `unit_id foreignId restrictOnDelete`;
`name varchar(255) index`; `description text nullable`;
`current_stock decimal(15,2) default 0`;
`reserved_stock decimal(15,2) default 0`;
`minimum_stock decimal(15,2) default 0`;
`storage_location varchar(255) nullable`; `image_path varchar(255) nullable`;
`is_active boolean default true index`; `created_at`; `updated_at`;
`deleted_at`.

Rumus: `available_stock = current_stock - reserved_stock`.

## 7. Barang Masuk

### `inventory_receipts`

Kolom:

`id`; `receipt_number varchar(80) unique`; `receipt_date datetime index`;
`source varchar(255)`; `reference_number varchar(255) nullable`;
`notes text nullable`; `status varchar(40) index`;
`created_by foreignId restrictOnDelete`;
`posted_by foreignId nullable nullOnDelete`; `posted_at timestamp nullable`;
`cancelled_by foreignId nullable nullOnDelete`;
`cancelled_at timestamp nullable`; `cancellation_reason text nullable`;
`created_at`; `updated_at`; `deleted_at`.

Status: `draft`, `posted`, `cancelled`.

### `inventory_receipt_items`

Kolom:

`id`; `inventory_receipt_id foreignId cascadeOnDelete`;
`item_id foreignId restrictOnDelete`; `item_code_snapshot varchar(80)`;
`item_name_snapshot varchar(255)`; `unit_snapshot varchar(100)`;
`quantity decimal(15,2)`; `unit_cost decimal(19,2) nullable`;
`notes text nullable`; `created_at`; `updated_at`.

Unique: `inventory_receipt_id + item_id`.

## 8. Penyesuaian Stok

### `stock_adjustments`

Kolom:

`id`; `adjustment_number varchar(80) unique`;
`adjustment_date datetime index`; `reason text`; `notes text nullable`;
`status varchar(40) index`; `created_by foreignId restrictOnDelete`;
`posted_by foreignId nullable nullOnDelete`; `posted_at timestamp nullable`;
`cancelled_by foreignId nullable nullOnDelete`;
`cancelled_at timestamp nullable`; `cancellation_reason text nullable`;
`created_at`; `updated_at`; `deleted_at`.

Status: `draft`, `posted`, `cancelled`.

### `stock_adjustment_items`

Kolom:

`id`; `stock_adjustment_id foreignId cascadeOnDelete`;
`item_id foreignId restrictOnDelete`; `item_code_snapshot varchar(80)`;
`item_name_snapshot varchar(255)`; `unit_snapshot varchar(100)`;
`system_quantity decimal(15,2)`; `physical_quantity decimal(15,2)`;
`difference_quantity decimal(15,2)`; `notes text nullable`; `created_at`;
`updated_at`.

Unique: `stock_adjustment_id + item_id`.

## 9. Permintaan Barang

### `inventory_requests`

Kolom:

`id`; `request_number varchar(80) unique`;
`requested_by foreignId restrictOnDelete`;
`employee_number_snapshot varchar(50) nullable`;
`requester_name_snapshot varchar(255)`;
`work_unit_snapshot varchar(255) nullable`; `request_date datetime index`;
`purpose text`; `notes text nullable`; `status varchar(40) index`;
`submitted_at timestamp nullable`;
`reviewed_by foreignId nullable nullOnDelete`;
`reviewed_at timestamp nullable`; `approved_at timestamp nullable`;
`rejected_at timestamp nullable`; `rejection_reason text nullable`;
`revision_note text nullable`;
`delivered_by foreignId nullable nullOnDelete`;
`delivered_at timestamp nullable`; `received_at timestamp nullable`;
`completed_at timestamp nullable`; `cancelled_at timestamp nullable`;
`expired_at timestamp nullable`; `admin_notes text nullable`; `created_at`;
`updated_at`; `deleted_at`.

Status: `draft`, `submitted`, `under_review`, `revision_required`, `approved`,
`partially_approved`, `waiting_stock`, `ready_for_delivery`, `delivered`,
`completed`, `rejected`, `cancelled`, `expired`.

### `inventory_request_items`

Kolom:

`id`; `inventory_request_id foreignId cascadeOnDelete`;
`item_id foreignId restrictOnDelete`; `item_code_snapshot varchar(80)`;
`item_name_snapshot varchar(255)`; `unit_snapshot varchar(100)`;
`requested_quantity decimal(15,2)`;
`approved_quantity decimal(15,2) nullable`;
`reserved_quantity decimal(15,2) default 0`;
`delivered_quantity decimal(15,2) nullable`; `notes text nullable`;
`admin_notes text nullable`; `created_at`; `updated_at`.

Unique: `inventory_request_id + item_id`.

### `inventory_request_status_histories`

Kolom:

`id`; `inventory_request_id foreignId cascadeOnDelete`;
`previous_status varchar(40) nullable`; `new_status varchar(40)`;
`notes text nullable`; `changed_by foreignId nullable nullOnDelete`;
`changed_at timestamp`.

## 10. Ledger Persediaan

### `stock_movements`

Kolom:

`id`; `movement_number varchar(80) unique`;
`reference_number varchar(80) index`; `item_id foreignId restrictOnDelete`;
`movement_type varchar(40) index`; `reference_type varchar(255) nullable`;
`reference_id bigint unsigned nullable`;
`quantity_in decimal(15,2) default 0`;
`quantity_out decimal(15,2) default 0`;
`stock_before decimal(15,2)`; `stock_after decimal(15,2)`;
`transaction_date datetime index`; `description text nullable`;
`created_by foreignId restrictOnDelete`; `created_at timestamp`.

Jenis: `initial_stock`, `stock_in`, `request_out`, `adjustment_in`,
`adjustment_out`, `return_in`, `damaged_out`.

Tabel ini tidak memiliki `updated_at` dan `deleted_at`.

## 11. Kendaraan

### `vehicles`

Kolom:

`id`; `public_id uuid unique`; `vehicle_code varchar(80) unique`;
`license_plate varchar(30) unique`; `brand varchar(255)`;
`model varchar(255)`; `year year nullable`; `color varchar(80) nullable`;
`chassis_number varchar(120) nullable unique`;
`engine_number varchar(120) nullable unique`;
`current_odometer decimal(12,1) default 0`; `status varchar(40) index`;
`registration_expiry_date date nullable index`;
`storage_location varchar(255) nullable`;
`responsible_person varchar(255) nullable`; `image_path varchar(255) nullable`;
`notes text nullable`; `is_active boolean default true index`; `created_at`;
`updated_at`; `deleted_at`.

Status: `available`, `reserved`, `borrowed`, `inspection`, `maintenance`,
`damaged`, `inactive`.

### `vehicle_loans`

Kolom:

`id`; `loan_number varchar(80) unique`;
`borrower_id foreignId restrictOnDelete`;
`employee_number_snapshot varchar(50) nullable`;
`borrower_name_snapshot varchar(255)`;
`work_unit_snapshot varchar(255) nullable`; `phone_snapshot varchar(30)`;
`vehicle_id foreignId restrictOnDelete`; `vehicle_code_snapshot varchar(80)`;
`license_plate_snapshot varchar(30)`; `vehicle_name_snapshot varchar(255)`;
`purpose text`; `destination varchar(255)`; `reason text nullable`;
`planned_start_at datetime index`; `planned_end_at datetime index`;
`actual_start_at datetime nullable`; `actual_end_at datetime nullable`;
`overdue_at timestamp nullable`; `status varchar(40) index`;
`reviewed_by foreignId nullable nullOnDelete`;
`reviewed_at timestamp nullable`; `approved_at timestamp nullable`;
`rejected_at timestamp nullable`; `rejection_reason text nullable`;
`cancelled_at timestamp nullable`; `cancellation_reason text nullable`;
`notes text nullable`; `created_at`; `updated_at`; `deleted_at`.

Status: `draft`, `submitted`, `under_review`, `approved`,
`ready_for_pickup`, `borrowed`, `awaiting_return_inspection`, `completed`,
`rejected`, `cancelled`, `return_issue`.

Keterlambatan ditandai `overdue_at` tanpa menghilangkan status `borrowed`.

### `vehicle_loan_status_histories`

Kolom:

`id`; `vehicle_loan_id foreignId cascadeOnDelete`;
`previous_status varchar(40) nullable`; `new_status varchar(40)`;
`notes text nullable`; `changed_by foreignId nullable nullOnDelete`;
`changed_at timestamp`.

### `vehicle_condition_checks`

Kolom:

`id`; `vehicle_loan_id foreignId cascadeOnDelete`;
`check_type varchar(30)`; `odometer decimal(12,1)`;
`fuel_level unsignedTinyInteger`; `overall_condition varchar(40)`;
`body_condition text`; `engine_condition text`; `tire_condition text`;
`equipment_condition text`; `damage_notes text nullable`;
`checked_by foreignId restrictOnDelete`; `checked_at timestamp`;
`borrower_confirmed_at timestamp nullable`; `created_at`; `updated_at`.

Unique: `vehicle_loan_id + check_type`.

`check_type`: `checkout`, `return`.

`overall_condition`: `good`, `needs_attention`, `damaged`.

## 12. Pemeliharaan

### `maintenance_records`

Kolom:

`id`; `maintenance_number varchar(80) unique`;
`vehicle_id foreignId restrictOnDelete`;
`source_vehicle_loan_id foreignId nullable nullOnDelete`;
`vehicle_snapshot varchar(255)`; `reported_by foreignId restrictOnDelete`;
`handled_by foreignId nullable nullOnDelete`;
`maintenance_type varchar(100)`; `complaint text`; `initial_condition text`;
`service_provider varchar(255) nullable`; `reported_date date index`;
`start_date date nullable`; `completion_date date nullable`;
`cost decimal(19,2) nullable`; `result text nullable`;
`final_condition text nullable`; `status varchar(40) index`; `created_at`;
`updated_at`; `deleted_at`.

Status: `reported`, `approved`, `in_progress`, `completed`,
`completed_with_notes`, `further_action_required`, `severely_damaged`,
`unserviceable`, `cancelled`.

Versi pertama hanya memelihara kendaraan. Barang persediaan tidak
diperlakukan sebagai aset tetap.

## 13. Bukti Digital

### `attachments`

Kolom:

`id`; `attachable_type varchar(255)`; `attachable_id bigint unsigned`;
`file_category varchar(80) index`; `disk varchar(50) default local`;
`original_name varchar(255)`; `stored_name varchar(255)`;
`file_path varchar(255)`; `mime_type varchar(120)`;
`file_size bigint unsigned`; `checksum char(64)`; `metadata json nullable`;
`uploaded_by foreignId restrictOnDelete`; `created_at`; `updated_at`;
`deleted_at`.

Kategori: `item_image`, `vehicle_image`, `vehicle_front`, `vehicle_back`,
`vehicle_left`, `vehicle_right`, `odometer`, `fuel`, `damage`, `receipt`,
`document`, `maintenance_before`, `maintenance_after`.

### `digital_signatures`

Kolom:

`id`; `signable_type varchar(255)`; `signable_id bigint unsigned`;
`signer_id foreignId restrictOnDelete`; `signer_name_snapshot varchar(255)`;
`employee_number_snapshot varchar(50) nullable`; `purpose varchar(100)`;
`image_path varchar(255)`; `transaction_hash char(64) index`;
`image_checksum char(64)`; `ip_address varchar(45) nullable`;
`user_agent text nullable`; `signed_at timestamp`; `created_at`; `updated_at`.

Unique: `signable_type + signable_id + signer_id + purpose`.

Tujuan: `inventory_request_submission`, `inventory_receipt_confirmation`,
`vehicle_loan_submission`, `vehicle_checkout_confirmation`,
`vehicle_return_confirmation`.

## 14. Pendukung Sistem

### `audit_logs`

Kolom:

`id`; `request_id uuid nullable index`;
`actor_id foreignId nullable nullOnDelete`; `event varchar(100) index`;
`module varchar(100) index`; `auditable_type varchar(255) nullable`;
`auditable_id bigint unsigned nullable`; `old_values json nullable`;
`new_values json nullable`; `ip_address varchar(45) nullable`;
`user_agent text nullable`; `url varchar(2048) nullable`;
`http_method varchar(10) nullable`; `created_at timestamp`.

Tabel ini tidak memiliki `updated_at` dan `deleted_at`.

### `document_sequences`

Kolom:

`id`; `document_type varchar(40)`; `year smallint unsigned`;
`month tinyint unsigned`; `last_number int unsigned default 0`; `created_at`;
`updated_at`.

Unique: `document_type + year + month`.

Prefix: `REQ`, `LOAN`, `MTC`, `STK-IN`, `STK-ADJ`, `MOV`.

### `settings`

Kolom:

`id`; `key varchar(255) unique`; `value json nullable`;
`group varchar(80) index`; `is_public boolean default false`; `created_at`;
`updated_at`.

### `notifications`

Mengikuti database notification Laravel: `id`, `type`, `notifiable_type`,
`notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`.

## 15. Relasi Utama

- `users` memiliki banyak permintaan, peminjaman, tanda tangan, dan audit.
- `users` memiliki role melalui `model_has_roles`.
- `item_categories` dan `units` memiliki banyak `items`.
- Setiap header barang masuk/penyesuaian/permintaan memiliki banyak detail.
- `items` memiliki banyak detail transaksi dan `stock_movements`.
- `vehicles` memiliki banyak `vehicle_loans` dan `maintenance_records`.
- `vehicle_loans` memiliki status history dan dua condition check.
- `attachments` dan `digital_signatures` berelasi polymorphic.
- `stock_movements` berelasi polymorphic ke detail transaksi sumber.

## 16. Aturan Integritas

1. `current_stock >= 0`, `reserved_stock >= 0`, dan
   `reserved_stock <= current_stock`.
2. Requested quantity lebih dari nol.
3. Approved quantity tidak melebihi requested quantity.
4. Delivered quantity tidak melebihi approved quantity.
5. Pegawai tidak dapat submit melebihi available stock.
6. Approval menambah reserved stock tanpa mengurangi current stock.
7. Penyerahan mengurangi current stock dan reserved stock secara atomik.
8. Posting barang masuk dan penyesuaian selalu membuat stock movement.
9. Stok tidak pernah diubah langsung melalui controller atau form master.
10. Dokumen posted, delivered, atau completed tidak dapat diedit.
11. Koreksi transaksi stok menggunakan reversal movement, bukan hapus ledger.
12. Satu kendaraan tidak boleh memiliki jadwal aktif yang bertumpang tindih.
13. Checkout check wajib sebelum kendaraan berstatus borrowed.
14. Return check wajib sebelum peminjaman completed.
15. Foto empat sisi, odometer, dan bahan bakar wajib saat serah terima.
16. Odometer akhir tidak boleh lebih kecil dari odometer awal.
17. Kerusakan pengembalian membuat maintenance record.
18. Kendaraan non-available tidak dapat dipilih.
19. Pengecekan stok dan jadwal diulang di dalam database transaction.
20. Setiap perubahan status membuat status history dan audit log.
21. Lampiran hanya tersedia melalui route yang memiliki otorisasi.
22. Pegawai hanya dapat melihat transaksi miliknya sendiri.
23. Nomor dokumen dibuat atomik menggunakan `document_sequences`.

## 17. Index Utama

- `items(category_id, is_active)`
- `inventory_requests(status, request_date)`
- `inventory_requests(requested_by, status)`
- `stock_movements(item_id, transaction_date)`
- `vehicle_loans(vehicle_id, planned_start_at, planned_end_at)`
- `vehicle_loans(borrower_id, status)`
- `maintenance_records(status, reported_date)`
- `audit_logs(module, created_at)`
- `audit_logs(actor_id, created_at)`
- Morph index pada semua relasi polymorphic

## 18. Urutan Migration

1. Tabel bawaan Laravel.
2. Tabel Spatie Permission.
3. Perluasan `users` dan token aktivasi.
4. Master persediaan.
5. Barang masuk dan penyesuaian stok.
6. Permintaan barang dan ledger stok.
7. Kendaraan dan peminjaman.
8. Pemeliharaan.
9. Lampiran, tanda tangan, audit, nomor dokumen, pengaturan, dan notifikasi.
