from __future__ import annotations

import unittest
from pathlib import Path
from tempfile import TemporaryDirectory

from scripts.verify_rag_knowledge_base import (
    ArticleParser,
    FetchResult,
    assess_document,
    assess_content,
    duplicate_assessment,
    inherited_overlap_flags,
    _has_substantive_body,
    _number_values,
    source_root,
    title_coverage,
)


class SourceVerificationTest(unittest.TestCase):
    def test_parser_extracts_article_metadata_and_omits_navigation(self) -> None:
        html = """<html><head>
        <title>Judul halaman</title>
        <meta property="og:title" content="BMKG Klarifikasi Pesan Gempa">
        <meta property="og:site_name" content="BMKG">
        <meta name="publisher" content="Facebook">
        </head><body><img class="share-icon" src="icon.png"><nav>Menu navigasi</nav><main><article>
        <h1>Judul artikel</h1><p>Isi artikel tentang gempa dan peringatan cuaca.</p>
        </article></main><time datetime="2021-01-08T02:19:55+00:00">2025-01-02</time><footer>Cookie dan footer</footer>
        <script>var hidden = 'menu';</script></body></html>"""
        parser = ArticleParser()
        parser.feed(html)

        page = parser.extract()

        self.assertEqual(page["title"], "BMKG Klarifikasi Pesan Gempa")
        self.assertEqual(page["publisher"], "BMKG")
        self.assertEqual(page["published_at"], "2025-01-02")
        self.assertIn("Isi artikel", page["text"])
        self.assertNotIn("Menu navigasi", page["text"])
        self.assertNotIn("Cookie dan footer", page["text"])
        self.assertNotIn("hidden", page["text"])

    def test_source_domain_match_rejects_generic_or_other_publisher_redirect(self) -> None:
        roots = {"bmkg.go.id"}
        self.assertEqual(source_root("gaw-bariri.bmkg.go.id", roots), "bmkg.go.id")
        self.assertIsNone(source_root("example.org", roots))

    def test_title_consistency_uses_significant_title_terms(self) -> None:
        self.assertGreaterEqual(
            title_coverage("BMKG Klarifikasi Pesan Gempa Susulan", "BMKG Klarifikasi Pesan Gempa"),
            0.6,
        )
        self.assertLess(
            title_coverage("BMKG Klarifikasi Pesan Gempa", "Berita Terkini Nasional"),
            0.35,
        )

    def test_supported_decision_requires_source_content_facts_and_metadata(self) -> None:
        document = {
            "id": "doc-gempa",
            "title": "BMKG Klarifikasi Pesan Gempa Susulan 7,5 SR",
            "content": (
                "BMKG menyatakan pesan berantai tentang potensi gempa susulan 7,5 SR bukan informasi resmi. "
                "Artikel menjelaskan warga tidak dapat memprediksi waktu lokasi dan kekuatan gempa. "
                "Peringatan gempa harus merujuk kanal resmi BMKG dan keterangan lembaga terkait."
            ),
            "source": "BMKG",
            "source_url": "https://www.bmkg.go.id/siaran-pers/contoh",
            "published_at": "2017-12-17",
            "topic": "bencana-cuaca",
        }
        page = FetchResult(
            "SOURCE_VERIFIED", 200, "https://www.bmkg.go.id/siaran-pers/contoh",
            "BMKG Klarifikasi Pesan Gempa Susulan 7,5 SR", "BMKG", "2017-12-17",
            (
                "BMKG Klarifikasi Pesan Gempa Susulan 7,5 SR. BMKG menyatakan pesan berantai "
                "mengenai gempa susulan 7,5 SR bukan informasi resmi. Gempa tidak dapat diprediksi "
                "secara tepat waktu, lokasi, dan kekuatan. Warga diminta memeriksa kanal resmi BMKG."
            ),
        )

        result = assess_document(document, page)

        self.assertEqual(result["source_status"], "SOURCE_VERIFIED")
        self.assertEqual(result["content_status"], "CONTENT_SUPPORTED")
        self.assertEqual(result["decision"], "APPROVED_BY_AUTOMATED_CHECKS")
        self.assertTrue(all(result["checks"].values()))

    def test_numeric_mismatch_is_not_marked_supported(self) -> None:
        document = {
            "id": "doc-claim",
            "title": "BMKG Klarifikasi Gempa",
            "content": "BMKG membahas klaim gempa berkekuatan 8,2 SR yang terjadi di wilayah tersebut.",
            "source": "BMKG",
            "source_url": "https://www.bmkg.go.id/berita/contoh",
            "published_at": "2025-01-01",
            "topic": "bencana-cuaca",
        }
        page = FetchResult(
            "SOURCE_VERIFIED", 200, "https://www.bmkg.go.id/berita/contoh",
            "BMKG Klarifikasi Gempa", "BMKG", "2025-01-01",
            "BMKG membahas gempa berkekuatan 6,1 SR di wilayah tersebut dan cuaca setempat.",
        )

        result = assess_content(document, page, "bmkg.go.id")

        self.assertIn("8.2", result["unsupported_numbers"])
        self.assertEqual(result["content_status"], "CONTENT_UNSUPPORTED")

    def test_indonesian_currency_formats_compare_as_the_same_amount(self) -> None:
        self.assertEqual(_number_values("BLT sebesar Rp900.000"), {"900000"})
        self.assertEqual(_number_values("Bantuan Rp. 900 Ribu"), {"900000"})
        self.assertEqual(_number_values("Rp58 T"), {"58000000000000"})

    def test_title_only_page_is_not_treated_as_available_article_content(self) -> None:
        title = "Kemenkes Luncurkan Gerakan Bersama Desa dan Kelurahan Siaga TBC untuk Eliminasi Tuberkulosis 2030"
        title_only_page = f"{title} Ditjen P2 {title} Ditjen P2"
        self.assertFalse(_has_substantive_body(title_only_page, title))
        self.assertTrue(
            _has_substantive_body(
                title_only_page + (
                    " Desa dan kelurahan diminta memperkuat deteksi gejala, pemeriksaan, "
                    "rujukan, pendampingan pengobatan, pencatatan kasus, dan edukasi warga "
                    "melalui koordinasi fasilitas kesehatan setempat."
                ),
                title,
            )
        )

    def test_duplicate_screen_keeps_independent_source_urls(self) -> None:
        first = {
            "id": "doc-one", "source": "BMKG", "title": "Klarifikasi Gempa Susulan",
            "source_url": "https://www.bmkg.go.id/article-one",
            "content": "BMKG menjelaskan informasi gempa susulan dan meminta warga mengikuti kanal resmi.",
        }
        second = {
            "id": "doc-two", "source": "CekFakta", "title": "Klarifikasi Gempa Susulan",
            "source_url": "https://cekfakta.com/article-one",
            "content": "BMKG menjelaskan informasi gempa susulan dan meminta warga mengikuti kanal resmi.",
        }

        exact, near = duplicate_assessment([first, second])

        self.assertEqual(exact, [])
        self.assertEqual(near, [])

    def test_prior_overlap_note_is_retained_as_unconfirmed_risk(self) -> None:
        with TemporaryDirectory() as directory:
            report = Path(directory) / "review-report.md"
            report.write_text(
                "| `doc-a` | title | url | date | topic | summary | status | "
                "potensi overlap dengan doc-b | REVIEW_REQUIRED |\n",
                encoding="utf-8",
            )
            self.assertEqual(
                inherited_overlap_flags(Path(directory)),
                [("doc-a", "potensi overlap dengan doc-b")],
            )


if __name__ == "__main__":
    unittest.main()
