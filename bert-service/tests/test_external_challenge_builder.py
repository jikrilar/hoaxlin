from pathlib import Path

from scripts.build_external_challenge import write_csv


def test_new_candidate_csv_has_explicit_utf8_bom_lf_and_preserves_field_text(tmp_path: Path) -> None:
    path = tmp_path / "candidates.csv"
    write_csv(path, [{"id": "fixture", "text": 'Tidak, "berubah"\nbaris kedua'}], ["id", "text"])
    assert path.read_bytes() == b'\xef\xbb\xbfid,text\nfixture,"Tidak, ""berubah""\nbaris kedua"\n'
