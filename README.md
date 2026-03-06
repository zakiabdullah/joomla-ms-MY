# Pakej Bahasa Melayu (ms-MY) untuk Joomla! 5

Pakej terjemahan Bahasa Melayu rasmi untuk Joomla! 5 CMS.

## Maklumat

| | |
|---|---|
| **Bahasa** | Bahasa Melayu (ms-MY) |
| **Versi Joomla** | 5.4.4 |
| **Versi Pakej** | 5.4.4.1 |

## Pemasangan

1. Muat turun fail ZIP terkini dari [Releases](https://github.com/zakiabdullah/joomla-ms-MY/releases)
2. Log masuk ke Pentadbir Joomla
3. Pergi ke **Sistem → Pasang → Sambungan**
4. Muat naik fail ZIP dan pasang
5. Aktifkan bahasa di **Sistem → Urus → Bahasa**

## Struktur Fail

```
language/ms-MY/              # Fail bahasa laman (site)
administrator/language/ms-MY/ # Fail bahasa pentadbir (admin)
api/language/ms-MY/          # Fail bahasa API
installation/language/ms-MY/ # Fail bahasa pemasangan
pkg_ms-MY.xml                # Manifes pakej
script.php                   # Skrip pemasangan
build.ps1                    # Skrip pembinaan (PowerShell)
```

## Pembinaan

```powershell
.\build.ps1
```

Fail output: `dist\ms-MY_joomla_lang_full_5.4.4.1.zip`

## Sumbangan

Sumbangan dialu-alukan! Sila buka [Issue](https://github.com/zakiabdullah/joomla-ms-MY/issues) atau hantar Pull Request.

## Lesen

GNU General Public License versi 2 atau lebih baharu; lihat [LICENSE.txt](https://www.gnu.org/licenses/gpl-2.0.html)