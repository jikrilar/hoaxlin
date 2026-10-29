import pytest

from app.decision_policy import decide, fingerprint


@pytest.mark.parametrize('confidence,label,reason', [(0.989999, 'meragukan', 'below_threshold'), (0.99, 'hoax', None), (0.990001, 'hoax', None)])
def test_release_threshold_boundary(confidence, label, reason):
    result = decide({'valid': 1 - confidence, 'hoax': confidence}, 0.99)
    assert result == {'raw_argmax': 'hoax', 'served_label': label, 'abstention_reason': reason}


def test_fingerprint_changes_for_every_inference_identity_component():
    identity = dict(model='a', tokenizer='b', tokenizer_config='c', calibration='d', decision='e', preprocessing='f', sequence=512)
    original = fingerprint(identity)
    assert original == fingerprint(dict(reversed(list(identity.items()))))
    for key in identity:
        assert fingerprint({**identity, key: 'changed'}) != original


def test_missing_decision_sidecars_cannot_silently_change_authority(tmp_path):
    from app.config import Settings
    from app.inference import ModelRuntime
    runtime = ModelRuntime(Settings('test', 'test', str(tmp_path), None, None, 1, 20000, 512, True))
    with pytest.raises(ValueError, match='sidecar'):
        runtime._load_sidecars()
