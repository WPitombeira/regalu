"""Reject incomplete/tampered evidence without modifying genuine browser captures."""
import argparse
import copy
import json
import os
from pathlib import Path
import tempfile

from event_join_smoke import STAGES, validate_report
from event_join_support import asset_path, checkout_identity, source_hashes, validate_png


def rejected(call, reason):
    try:
        call()
    except AssertionError as error:
        assert str(error) == reason, f'wrong_rejection:{reason}'
    else:
        raise AssertionError(f'accepted_mutation:{reason}')


def qualify(report_path):
    report = json.loads(report_path.read_text())
    output = report_path.parent
    identity, hashes = checkout_identity(), source_hashes()
    validate_report(report, output, identity, hashes)
    rejected_cases = []

    def reject_report(reason, mutate):
        changed = copy.deepcopy(report)
        mutate(changed)
        rejected(lambda: validate_report(changed, output, identity, hashes), reason)
        rejected_cases.append(reason)

    reject_report('case_matrix', lambda r: r['cases'].__setitem__(1, copy.deepcopy(r['cases'][0])))
    reject_report('incomplete_report', lambda r: r.__setitem__('complete', False))
    reject_report('case_incomplete', lambda r: r['cases'][0].__setitem__('passed', False))
    reject_report('source_drift', lambda r: r['source_hashes'].__setitem__('composer.json', '0' * 64))
    reject_report('checkout_identity', lambda r: r['checkout'].__setitem__('commit', '0' * 40))
    reject_report('control_overflow', lambda r: r['cases'][0]['geometry'][STAGES[0]].__setitem__('controls', []))
    reject_report('checkpoints', lambda r: r['cases'][0]['screenshots'].pop(STAGES[0]))
    reject_report('http_contract', lambda r: r['cases'][0]['http'].__setitem__('closed', 200))
    reject_report('journey_checks', lambda r: r['cases'][0]['checks'].__setitem__('revoked_refused', False))
    reject_report('screenshot_hash', lambda r: r['cases'][0]['screenshots'].__setitem__(STAGES[0], '0' * 64))

    scratch = os.environ.get('RUNNER_TEMP') or os.environ.get('TMPDIR')
    assert scratch, 'scratch_required'
    with tempfile.TemporaryDirectory(prefix='regalu-join-validator-', dir=scratch) as temporary:
        directory = Path(temporary)
        original = report['cases'][0]
        genuine = (output / original['case'] / (STAGES[0] + '.png')).read_bytes()
        # Only private negative copies are changed; the real receipt remains untouched.
        for name, data, reason in (
            ('signature', genuine[:8], 'png_incomplete'),
            ('truncated', genuine[:-1], 'png_truncated'),
            ('corrupt', genuine[:29] + bytes([genuine[29] ^ 1]) + genuine[30:], 'png_crc'),
        ):
            path = directory / (name + '.png')
            path.write_bytes(data)
            rejected(lambda: validate_png(path, original['width']), reason)
            rejected_cases.append(reason)
        path = directory / 'dimensions.png'
        path.write_bytes(genuine)
        rejected(lambda: validate_png(path, original['width'] + 1), 'png_dimensions')
        rejected_cases.append('png_dimensions')

        build = directory / 'build'
        build.mkdir()
        inside = build / 'valid.js'
        inside.write_text('// synthetic, non-secret asset\n')
        outside = directory / 'outside.js'
        outside.write_text('// synthetic, non-secret outside file\n')
        (build / 'escape.js').symlink_to(outside)
        assert asset_path(build, 'valid.js') == inside.resolve()
        for name, reason in (
            ('../outside.js', 'asset_escape'),
            (str(outside), 'asset_escape'),
            ('C:/outside.js', 'asset_escape'),
            ('..\\outside.js', 'asset_name'),
            ('escape.js', 'asset_escape'),
        ):
            rejected(lambda: asset_path(build, name), reason)
            rejected_cases.append(reason)
    assert not directory.exists(), 'validator_fixture_leak'
    # Prove qualification itself did not change the bound sources or genuine receipt.
    assert report == json.loads(report_path.read_text()), 'report_modified'
    assert identity == checkout_identity() and hashes == source_hashes(), 'source_drift'
    validate_report(report, output, identity, hashes)
    print(json.dumps({'passed': True, 'rejected_mutations': len(rejected_cases),
                      'cases': len(report['cases']),
                      'screenshots': sum(len(case['screenshots']) for case in report['cases'])}))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--report', required=True, type=Path)
    args = parser.parse_args()
    qualify(args.report)


if __name__ == '__main__':
    main()
