"""Qualify the toast evidence validator using a genuine, unchanged browser receipt."""
import argparse
from copy import deepcopy
import json
import os
from pathlib import Path
import struct
import tempfile
import zlib

from toast_feedback_smoke import CASES, case_key, checkout_identity, source_hashes, validate_png, validate_report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('report', type=Path)
    args = parser.parse_args()
    report = json.loads(args.report.read_text())
    output = args.report.parent
    hashes, checkout = source_hashes(), checkout_identity()
    validate_report(report, output, hashes, checkout)
    rejected = []

    def reject(name, change):
        candidate = deepcopy(report)
        change(candidate)
        try:
            validate_report(candidate, output, hashes, checkout)
        except (AssertionError, KeyError, TypeError, ValueError, OSError, zlib.error):
            rejected.append(name)
            return
        raise AssertionError('validator accepted ' + name)

    def result_change(key, value):
        return lambda candidate: candidate['results'][0].__setitem__(key, value)

    def card_change(key, value):
        return lambda candidate: candidate['results'][0]['geometry']['profile']['cards'][0].__setitem__(key, value)

    reject('duplicate_case', lambda candidate: candidate['results'].__setitem__(1, deepcopy(candidate['results'][0])))
    reject('missing_case', lambda candidate: candidate['results'].pop())
    reject('incomplete_report', lambda candidate: candidate.__setitem__('complete', False))
    reject('partial_case', result_change('stage', 'started'))
    reject('failed_case', result_change('passed', False))
    reject('page_error', result_change('page_errors', ['page_error']))
    for key in ('profile_persisted', 'enter_dismissed', 'space_dismissed', 'pointer_dismissed',
                'independent_dismissal', 'escaped_message', 'fixture_cleaned'):
        reject('false_' + key, result_change(key, False))
    reject('early_expiry', result_change('auto_hide_seconds', 0.5))
    reject('missing_checkpoint', lambda candidate: candidate['results'][0]['geometry'].pop('long'))
    reject('empty_geometry', lambda candidate: candidate['results'][0]['geometry']['profile'].__setitem__('cards', []))
    reject('wrong_viewport', lambda candidate: candidate['results'][0]['geometry']['profile'].__setitem__('viewport', 1280))
    reject('page_overflow', lambda candidate: candidate['results'][0]['geometry']['profile'].__setitem__('content', 9999))
    reject('offscreen_control', lambda candidate: candidate['results'][0]['geometry']['profile']['cards'][0]['button'].__setitem__('right', 9999))
    reject('small_target', lambda candidate: candidate['results'][0]['geometry']['profile']['cards'][0]['button'].__setitem__('height', 32))
    reject('low_text_contrast', card_change('messageContrast', 1))
    reject('low_control_contrast', card_change('buttonContrast', 1))
    reject('nonfinite_contrast', card_change('buttonContrast', float('inf')))
    reject('occluded_toast', card_change('unobscured', False))
    reject('occluded_button', card_change('buttonOnTop', False))
    reject('empty_text_hit_tests', card_change('messageOnTop', []))
    reject('duplicate_identity', lambda candidate: candidate['results'][0]['geometry']['stack']['cards'][1].__setitem__(
        'id', candidate['results'][0]['geometry']['stack']['cards'][0]['id']))
    reject('dropped_screenshot', lambda candidate: candidate['results'][0]['screenshots'].pop())
    reject('duplicate_screenshot', lambda candidate: candidate['results'][0]['screenshots'].__setitem__(1, candidate['results'][0]['screenshots'][0]))
    source = next(iter(hashes))
    reject('missing_source_hash', lambda candidate: candidate['source_hashes'].pop(source))
    reject('changed_source_hash', lambda candidate: candidate['source_hashes'].__setitem__(source, '0' * 64))
    reject('extra_source_hash', lambda candidate: candidate['source_hashes'].__setitem__('unknown.php', '0' * 64))
    for key in ('commit', 'tree'):
        reject('changed_checkout_' + key, lambda candidate, key=key: candidate['checkout'].__setitem__(key, '0' * 40))
    reject('changed_checkout_dirty', lambda candidate: candidate['checkout'].__setitem__('dirty', not checkout['dirty']))

    # Corrupt only task-owned copies; never rewrite the genuine evidence.
    first = report['results'][0]
    width = next(case[2] for case in CASES if case_key(*case) == first['case'])
    image = (output / first['screenshots'][0]).read_bytes()
    crc_bad = bytearray(image)
    crc_bad[29] ^= 1
    def chunk(kind, payload):
        return struct.pack('>I', len(payload)) + kind + payload + struct.pack('>I', zlib.crc32(kind + payload))
    malformed = {
        'signature_only': image[:8],
        'truncated_png': image[:-10],
        'bad_crc': bytes(crc_bad),
        'bad_deflate': image[:33] + chunk(b'IDAT', b'not compressed image data') + chunk(b'IEND', b''),
    }
    scratch = os.environ.get('RUNNER_TEMP') or os.environ.get('TMPDIR')
    assert scratch, 'scratch_required'
    with tempfile.TemporaryDirectory(prefix='regalu-toast-validator-', dir=scratch) as temporary:
        path = Path(temporary) / 'negative.png'
        for name, data in malformed.items():
            path.write_bytes(data)
            try:
                validate_png(path, width)
            except (AssertionError, ValueError, zlib.error):
                rejected.append(name)
                continue
            raise AssertionError('validator accepted ' + name)
    validate_report(report, output, hashes, checkout)
    print(json.dumps({'validator_passed': True, 'rejected_mutations': len(rejected), 'mutations': rejected}))


if __name__ == '__main__':
    main()
