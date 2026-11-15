# Menyumbang kepada pek bahasa ms-MY

Terima kasih kerana membantu menterjemah Joomla! ke Bahasa Melayu. Dokumen ini
menerangkan cara repositori disusun dan semakan yang perlu lulus sebelum
perubahan diterima.

## Sebelum mula

1. Baca [GLOSSARY.md](GLOSSARY.md). Ia mengandungi istilah standard dan panduan gaya.
2. Untuk perubahan kecil, contohnya membetulkan satu rentetan, terus hantar Pull Request.
3. Untuk menukar istilah yang digunakan di banyak tempat, buka Issue dahulu.

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
ialah sumber utama. Kunci yang hanya wujud dalam satu versi tidak mendatangkan
masalah, kerana Joomla mengabaikan kunci yang tidak digunakan.

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
| **Translation QA** | Push/PR yang mengubah `.ini` | Ralat format, atau placeholder tidak sepadan dengan en-GB `5.4-dev`/`6.2-dev` |
| **Build Joomla Language Pack** | Push/PR/tag | Build gagal atau pakej kosong |
| **Sync Check with Joomla CMS** | Setiap Ahad | Hanya memberi amaran tentang kunci baharu daripada Joomla (atau gagal dalam mod `strict`) |

## Proses keluaran (penyelenggara)

1. Kemas kini `packageVersion` dalam `build/build.config.json` dan `<version>` dalam `pkg_ms-MY.xml`.
2. Kemas kini [CHANGELOG.md](CHANGELOG.md).
3. Kemas kini `RELEASE_NOTES_DRAFT.md` (badan GitHub Release), kemudian jalankan `pwsh ./build.ps1` untuk menguji.
   Checksum rasmi ialah `SHA256SUMS.txt` yang dijana oleh CI, kerana zip tempatan mempunyai cap masa yang berbeza.
4. Commit, kemudian tag mengikut format `<versi Joomla 5>v<n>`, cth. `5.4.10v1`, dan push tag tersebut.
   CI akan membina pakej dan mencipta GitHub Release bersama `SHA256SUMS.txt`.

## Lesen

Dengan menyumbang, anda bersetuju sumbangan anda dilesenkan di bawah
GNU General Public License versi 2 atau lebih baharu.
