# Menyumbang kepada pek bahasa ms-MY

Terima kasih kerana membantu menterjemah Joomla! ke Bahasa Melayu. Dokumen ini
menerangkan cara repositori disusun dan semakan yang perlu lulus sebelum
perubahan diterima.

## Sebelum mula

1. Baca [GLOSSARY.md](GLOSSARY.md). Ia mengandungi istilah standard dan panduan gaya.
2. Untuk perubahan kecil, contohnya membetulkan satu rentetan, terus hantar Pull Request **ke cabang `j5`**.
3. Untuk menukar istilah yang digunakan di banyak tempat, buka Issue dahulu.
4. Senarai rentetan yang perlu diterjemah ada dalam Issue berlabel
   [`sync`](https://github.com/zakiabdullah/joomla-ms-MY/issues?q=is%3Aopen+label%3Async).
   Issue ini dibuka dan dikemas kini secara automatik setiap minggu.

## Struktur repositori

| Folder | Kandungan |
|---|---|
| `language/ms-MY/` | Bahagian depan laman (site) |
| `administrator/language/ms-MY/` | Panel pentadbir |
| `api/language/ms-MY/` | API web services |
| `installation/language/ms-MY/` | Pemasang Joomla |
| `overrides/<target>/…` | Rentetan yang berbeza mengikut versi utama Joomla (lihat di bawah) |
| `build/` | Konfigurasi build dan alat semakan |

### Satu pohon terjemahan, dua versi Joomla

Pohon fail di atas digunakan untuk membina **kedua-dua** pakej
`pkg_ms-MY_j5.zip` (Joomla 5) dan `pkg_ms-MY_j6.zip` (Joomla 6). Cabang `j5`
ialah sumber utama. Cabang `j6` **dijana** daripada `j5` secara automatik: pohon `j5`
ditambah penyesuaian Joomla 6 (override `overrides/j6/` diaplikasikan, manifest versi 6.x,
`translationlist_6`, `minimumJoomla` 6.0). **Jangan commit terus ke `j6`.**

Kedua-dua cabang dibaca terus oleh projek
[core-translations](https://github.com/joomla/core-translations) Joomla setiap malam
(`j5` pada 21:15 UTC, `j6` pada 22:00 UTC), kemudian disegerakkan ke Crowdin dan
**pemasang teras Joomla**. Jadi apa yang ada dalam `j5`/`j6` sampai kepada semua
pengguna yang memasang Joomla dalam Bahasa Melayu. Kunci yang hanya wujud dalam satu versi tidak
mendatangkan masalah, kerana Joomla mengabaikan kunci yang tidak digunakan.

Jika **nilai** sesuatu kunci mesti berbeza antara versi, contohnya Joomla 6
menambah placeholder `%2$s`, simpan:

- versi yang serasi dengan Joomla 5 dalam fail biasa, dan
- versi Joomla 6 dalam `overrides/j6/<laluan yang sama>`, cth.
  `overrides/j6/administrator/language/ms-MY/com_installer.ini`.

`build.ps1` akan menggantikan kunci tersebut semasa membina pakej j6 sahaja.
Placeholder yang tidak sepadan boleh menyebabkan ralat PHP (`ArgumentCountError`),
jadi jangan masukkan bentuk Joomla 6 ke dalam fail kongsi.

## Format fail

- UTF-8 **tanpa BOM**, hujung baris LF (ditetapkan oleh `.gitattributes` dan `.editorconfig`).
- Satu `KEY="nilai"` setiap baris. Kunci mesti HURUF BESAR dan tidak berganda.
- Petikan berganda dalam nilai mesti ditulis `\"`.
- Ikut susunan kunci dalam fail en-GB supaya mudah dibandingkan.

## Semakan tempatan

Keperluan: PHP 8.1+ dan PowerShell 7 (untuk build).

```bash
# Semakan format dan sintaks
php build/check-translation.php

# Bandingkan dengan Joomla rasmi (kunci hilang dan placeholder)
git clone --depth 1 --filter=blob:none --sparse -b 6.2-dev https://github.com/joomla/joomla-cms.git ../joomla-cms
git -C ../joomla-cms sparse-checkout set language/en-GB administrator/language/en-GB api/language/en-GB installation/language/en-GB
php build/check-translation.php --ref=../joomla-cms --target=j6

# Bina pakej
pwsh ./build.ps1
```

`--target=j5|j6` mengaplikasikan `overrides/<target>/` sebelum perbandingan,
sama seperti yang dibuat oleh build.

## Semakan automatik (GitHub Actions)

| Aliran kerja | Bila | Gagal jika |
|---|---|---|
| **Translation QA** | Setiap push/PR | Ralat format, istilah yang dilarang oleh glosari, atau placeholder tidak sepadan dengan en-GB `5.4-dev`/`6.2-dev` |
| **Build Joomla Language Pack** | Push/PR/tag | Build gagal atau pakej kosong |
| **Sync Check with Joomla CMS** | Setiap Ahad | Membuka/mengemas kini Issue `sync` jika Joomla menambah rentetan baharu, dan menutupnya apabila lengkap |
| **Sync j6 with j5** | Push ke `j5` | Pohon `j6` yang dijana tidak sah untuk Joomla 6 (placeholder/istilah dilindungi) |

Merge ke `j5` memerlukan semakan **Translation QA** dan **Build** lulus.

### Istilah yang dilarang

`build/glossary-rules.json` menyenaraikan istilah yang tidak boleh digunakan
(cth. *perihalan*, *penggantian*, *emel*, *baru* untuk "new"). Semakan hanya
melihat teks yang dibaca pengguna, jadi tag HTML dan URL diabaikan. Untuk
pengecualian yang wajar, tambah nama kunci ke senarai `allow`.

### Membuang kunci lapuk

Apabila Joomla membuang rentetan, keluarkan kunci yang tiada dalam **mana-mana**
versi yang disokong:

```bash
# Laporan sahaja
php build/prune-obsolete.php --ref=../cms-5.4.10 --ref=../cms-5.4-dev \
                             --ref=../cms-6.2.0  --ref=../cms-6.2-dev

# Ubah fail: tambah --write pada arahan yang sama
php build/prune-obsolete.php ... --write
```

## Proses keluaran (penyelenggara)

1. Jalankan `php build/bump.php --j5=<versi> --j6=<versi> --pack=1`. Ia mengemas kini `build.config.json`, `pkg_ms-MY.xml` dan manifest sumber.
2. Dalam [CHANGELOG.md](CHANGELOG.md), tukar tajuk `## [Unreleased]` kepada `## [<tag>] - <tarikh>`.
   Bahagian ini menjadi badan GitHub Release secara automatik (`build/release-notes.php`).
3. Jalankan `pwsh ./build.ps1` dan `php build/release-notes.php <tag>` untuk menguji.
   Build boleh dihasilkan semula: commit yang sama menghasilkan zip yang sama bait demi bait,
   jadi checksum tempatan boleh dibandingkan dengan `SHA256SUMS.txt` dalam Release.
4. Commit, kemudian tag mengikut format `<versi Joomla 5>v<n>`, cth. `5.4.10v1`, dan push tag tersebut.
   CI akan membina pakej dan mencipta GitHub Release bersama `SHA256SUMS.txt`.
   CI gagal jika CHANGELOG tiada bahagian untuk tag itu, atau tag tidak sepadan dengan versi j5.

## Lesen

Dengan menyumbang, anda bersetuju sumbangan anda dilesenkan di bawah
GNU General Public License versi 2 atau lebih baharu.
