# Aset identitas historis

BantuKasir hanya codename internal; [DESIGN](../../DESIGN.md) dan [04](../../docs/04-AUTH-RBAC-SECURITY.md) mewajibkan BusinessProfile runtime dengan identitas client resmi. Aset di direktori ini dipertahankan tanpa perubahan binary, tetapi **tidak menjadi default branding aplikasi produksi atau authority token**.

| Aset | Provenance / batas penggunaan |
| --- | --- |
| [LATANSA mark](source/latansa-official-colored-mark.png) | Owner menyebutnya identitas resmi LATANSA pada 20 September 2026. PNG 6250×6250, 6.680.241 byte; SHA-256 `964e1a8644d75de8b5dbd37b4fae0e5af5f8c961f0acb60000e963ef4356f4ef`. Original tetap byte-identik; tidak menyatakan lisensi pihak ketiga yang lebih luas |
| [Logo codename](bantukasir-logo.svg) | Aset prototype BantuKasir terdahulu, bukan logo resmi client produksi |

Tidak ada approval untuk memakai LATANSA pada client lain, mengimpor palet lamanya atau mengubah original. Jika client nanti memakai logo miliknya, owner memberi sumber/izin resmi; runtime memvalidasi raster sesuai04 dan snapshot mereferensikan revision/aset privat. Tidak perlu mendesain merek atau mengoptimalkan aset ini dalam rebaseline. T04/T23/T32 menangani boundary identitas, visual dan reproduksi dokumen.
