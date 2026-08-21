from __future__ import annotations

import json
from pathlib import Path

from typer.testing import CliRunner

from dataset.prepare import app

runner = CliRunner()


def _write_komdigi_csv(path: Path, n: int = 12) -> None:
    header = [
        "id",
        "url",
        "title",
        "slug",
        "published_at",
        "view_count",
        "excerpt",
        "body_html",
        "body_text",
        "main_image_url",
        "category",
        "tags",
        "topics",
    ]
    subjects = [
        ("undian berhadiah bank nasional", "keuangan", "Penipuan"),
        ("kartu atm bantuan siswa", "pendidikan", "Penipuan"),
        ("vaksin polio membahayakan kesehatan", "kesehatan", "Kesehatan"),
        ("el nino sangat kuat melanda jawa", "cuaca", "Hoaks"),
        ("pajak rumah kontrakan tahun depan", "ekonomi", "Program/Kebijakan"),
        ("tautan pendaftaran kuota internet", "telekomunikasi", "Penipuan"),
        ("gaji manajer koperasi desa", "ekonomi", "Hoaks"),
        ("bendera robek di monas", "nasional", "Hoaks"),
        ("dokumen bpom bocor rahasia", "kesehatan", "Kesehatan"),
        ("sertifikat tanah balik nama", "pertanahan", "Program/Kebijakan"),
        ("presiden sita penggilingan padi", "pertanian", "Pejabat Publik"),
        ("snack luppo mengandung pil", "pangan", "Hoaks"),
    ]
    rows = []
    for i in range(n):
        subject, angle, topic = subjects[i % len(subjects)]
        claim = (
            f"Beredar unggahan di media sosial yang mengeklaim {subject} "
            f"dengan narasi {angle} dan meminta masyarakat segera mengisi "
            f"kuesioner yang mencurigakan melalui tautan yang beredar luas "
            f"di berbagai platform dan grup percakapan whatsapp dan telegram "
            f"selama beberapa hari terakhir tanpa henti. Faktanya, klaim "
            f"tersebut tidak benar dan telah dibantah oleh pihak yang "
            f"berwenang melalui akun resmi mereka."
        )
        rows.append(
            {
                "id": str(1000 + i),
                "url": f"https://www.komdigi.go.id/berita/berita-hoaks/detail/hoaks-{i}",
                "title": f"[HOAKS] Judul {i}",
                "slug": f"hoaks-{i}",
                "published_at": f"2026-08-0{i % 9 + 1} 10:00:00",
                "view_count": str(10 * i),
                "excerpt": "",
                "body_html": f"<p>{claim}</p>",
                "body_text": claim,
                "main_image_url": "",
                "category": "Klarifikasi Hoaks",
                "tags": "hoaks hari ini",
                "topics": topic,
            }
        )
    # one exact duplicate
    rows.append(dict(rows[0], id="9999"))
    with path.open("w", encoding="utf-8", newline="") as handle:
        import csv

        writer = csv.DictWriter(handle, fieldnames=header)
        writer.writeheader()
        writer.writerows(rows)


def test_prepare_cli_end_to_end(tmp_path: Path):
    source = tmp_path / "komdigi.csv"
    _write_komdigi_csv(source, n=12)
    out = tmp_path / "processed"

    result = runner.invoke(
        app,
        [
            "--source",
            str(source),
            "--out",
            str(out),
            "--dataset-name",
            "test-komdigi",
            "--version",
            "9.9.9",
        ],
    )
    assert result.exit_code == 0, result.output

    for name in ("all.jsonl", "train.jsonl", "val.jsonl", "test.jsonl", "rejected.jsonl"):
        assert (out / name).is_file(), name

    all_records = [json.loads(line) for line in (out / "all.jsonl").read_text().splitlines()]
    assert len(all_records) == 12  # 13 rows - 1 exact duplicate
    assert all(r["label"] == "hoax" for r in all_records)
    assert all(r["record_id"] for r in all_records)
    assert all(r["claim_group"] for r in all_records)
    assert all("faktanya" not in r["text"].lower() for r in all_records)
    assert all("faktanya" in r["full_text"].lower() for r in all_records)

    rejected = [json.loads(line) for line in (out / "rejected.jsonl").read_text().splitlines()]
    assert any(r["reason"] == "exact_duplicate" for r in rejected)

    manifest = json.loads((out / "manifest.json").read_text(encoding="utf-8"))
    assert manifest["dataset"]["name"] == "test-komdigi"
    assert manifest["dataset"]["version"] == "9.9.9"
    assert manifest["stats"]["records_kept"] == 12
    assert manifest["stats"]["label_counts"] == {"hoax": 12}
    assert manifest["split"]["mode"] == "group"
    for split in ("train", "val", "test"):
        assert manifest["split"]["splits"][split]["rows"] > 0
    for artifact in manifest["artifacts"]:
        assert artifact["sha256"]
    assert "input_files" in manifest
    assert (out / "data_card.md").is_file()


def test_prepare_cli_rejects_bad_ratios(tmp_path: Path):
    source = tmp_path / "komdigi.csv"
    _write_komdigi_csv(source)
    result = runner.invoke(
        app,
        ["--source", str(source), "--train-ratio", "0.5", "--val-ratio", "0.5"],
    )
    assert result.exit_code != 0


def test_prepare_cli_time_mode(tmp_path: Path):
    source = tmp_path / "komdigi.csv"
    _write_komdigi_csv(source, n=12)
    out = tmp_path / "processed-time"
    result = runner.invoke(
        app,
        ["--source", str(source), "--out", str(out), "--split-mode", "time"],
    )
    assert result.exit_code == 0, result.output
    manifest = json.loads((out / "manifest.json").read_text(encoding="utf-8"))
    assert manifest["split"]["mode"] == "time"


def test_prepare_cli_rejects_bad_split_mode(tmp_path: Path):
    source = tmp_path / "komdigi.csv"
    _write_komdigi_csv(source)
    result = runner.invoke(
        app, ["--source", str(source), "--split-mode", "banana"]
    )
    assert result.exit_code != 0