# Glosari & Panduan Gaya ms-MY

Rujukan istilah untuk semua penterjemah pek bahasa Melayu Joomla!. Istilah di
bawah diambil daripada terjemahan yang sudah digunakan secara meluas dalam pek
ini. Gunakannya secara konsisten. Jika anda ingin menukar sesuatu istilah, buka
[Issue](https://github.com/zakiabdullah/joomla-ms-MY/issues) dahulu supaya
perubahan dibuat serentak di semua fail.

Istilah yang dilarang dikuatkuasakan secara automatik oleh
[`build/glossary-rules.json`](build/glossary-rules.json): CI akan gagal jika ia digunakan.

## Panduan gaya

| Perkara | Peraturan | Contoh |
|---|---|---|
| Sapaan | Gunakan **anda** (huruf kecil di tengah ayat). | "Sila semak e-mel anda." |
| Ejaan | Ikut ejaan baku DBP. | **baharu** (bukan *baru* untuk "new"), **e-mel** (bukan *emel*) |
| Huruf besar | Ikut gaya en-GB: label dan tajuk guna Huruf Besar Setiap Perkataan, ayat guna huruf besar di awal sahaja. | "Simpan & Tutup", "Item telah disimpan." |
| Nama produk & jenama | Jangan diterjemah. | Joomla!, TinyMCE, CodeMirror, Cassiopeia, Atum, OpenSearch |
| Placeholder | Kekalkan `%s`, `%d`, `%1$s` dan token `{nama}` dengan tepat. Susunan boleh diubah menggunakan `%1$s`/`%2$s`. | "Menyahpasang %1$s %2$s berjaya." |
| HTML | Kekalkan semua tag dan atribut. Terjemahkan teks dalam atribut `title`/`alt`. | `<a href='{itemlink}' title='Sunting {type} {title}'>` |
| Petikan berganda | Dalam nilai INI, tulis `\"`. Petikan tunggal `'` dibenarkan. | `"Klik \"Simpan\""` |
| Komen di hujung baris | Jika en-GB ada komen `; Do not translate ...`, kekalkan. | `JFIELD_MEDIA_DOWNLOAD_FILE="Muat turun {file}" ; ...` |
| *Do not translate* | Istilah yang en-GB tandakan *Do not translate 'X'* mesti kekal dalam terjemahan (disemak oleh CI). | `"Paksa HTTP Headers"` |
| Ringkas | Label butang dan menu mesti pendek. Jangan tambah perkataan yang tiada dalam sumber. | "Status", bukan "Status Aliran Kerja" |

## Istilah teras

| English | Bahasa Melayu | Nota |
|---|---|---|
| Access | Akses | |
| Access Level | Tahap Akses | |
| Administrator | Pentadbir | Bahagian pentadbiran: *Pentadbiran* |
| Alias | Alias | |
| All | Semua | |
| Archived | Diarkibkan | Kata kerja: *arkibkan* |
| Article | Artikel | |
| Authenticator (app) | Aplikasi pengesah | |
| Batch | Kelompok | |
| Browser | Pelayar | |
| Cache | Cache | Tidak diterjemah |
| Cancel | Batal | |
| Category | Kategori | |
| Check-in | Daftar Masuk | *didaftar masuk* |
| Check-out | Daftar Keluar | |
| Close | Tutup | |
| Component | Komponen | |
| Configure ACL & Options | Konfigurasi ACL & Pilihan | |
| Create | Cipta | |
| Default | Lalai | |
| Delete | Padam | *dipadam* |
| Description | Penerangan | Bukan *perihalan* |
| Disabled | Dilumpuhkan | |
| Download | Muat turun | |
| Edit | Sunting | Bukan *edit* |
| Email | E-mel | |
| Enabled | Diaktifkan | |
| Extension | Sambungan | |
| Featured | Pilihan | |
| Field | Medan | Custom Fields: *Medan Tersuai* |
| File | Fail | |
| Folder | Folder | |
| Header (HTTP) | Pengepala | Kekalkan *HTTP Headers* jika en-GB menyatakan *Do not translate* |
| Help | Bantuan | |
| Hits | Tontonan | |
| Install | Pasang | |
| Language | Bahasa | |
| Layout | Susun Atur | |
| Link | Pautan | |
| Log in / Log out | Log Masuk / Log Keluar | |
| Menu Item | Item Menu | |
| Module | Modul | |
| New | Baharu | |
| Note | Nota | |
| Options | Pilihan | |
| Ordering | Susunan | |
| Orphaned | Yatim | |
| Override | Pengatasan | Bahasa dan templat |
| Password | Kata Laluan | |
| Plugin | Pemalam | |
| Published | Diterbitkan | Penerbitan = *Publishing* |
| Register | Daftar | |
| Save / Save & Close | Simpan / Simpan & Tutup | |
| Schema | Skema | *Schema.org* ialah nama, tidak diterjemah |
| Search | Carian (kata nama), Cari (kata kerja) | |
| Settings | Tetapan | |
| Site | Laman | Website: *laman web* |
| Shortcut (keyboard) | Pintasan (papan kekunci) | |
| Stage / Transition | Peringkat / Peralihan | Aliran kerja |
| Tag | Tag | |
| Template | Templat | |
| Title | Tajuk | |
| Trashed | Dibuang | Empty Trash: *Kosongkan Tong Sampah* |
| Uninstall | Nyahpasang | *menyahpasang* |
| Unpublished | Tidak Diterbitkan | |
| Update | Kemas kini | |
| Upload | Muat naik | |
| User | Pengguna | |
| Username | Nama Pengguna | |
| Version | Versi | |
| Workflow | Aliran Kerja | |
| Wrapper | Pembalut | |
