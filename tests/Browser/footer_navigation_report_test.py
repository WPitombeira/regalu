"""Reject mutated copies of a genuine full-matrix browser receipt; never rewrite it."""
import argparse
from copy import deepcopy
import hashlib
import json
import os
from pathlib import Path
import tempfile

from footer_navigation_smoke import asset_path, validate_png, validate_report


def rejected(action):
    try:
        action()
    except (AssertionError, KeyError, TypeError, ValueError, OSError):
        return
    raise AssertionError('invalid evidence was accepted')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('report', type=Path)
    args = parser.parse_args()
    original_bytes = args.report.read_bytes()
    report = json.loads(original_bytes)
    output = args.report.parent
    validate_report(report, output)
    mutations = [
        lambda r: r.update(complete=False),
        lambda r: r.update(schema=99),
        lambda r: r.update(fixture_cleanup=False),
        lambda r: r['checkout'].update(commit='0' * 40),
        lambda r: r['checkout'].update(tree='0' * 40),
        lambda r: r['checkout'].update(dirty=not r['checkout']['dirty']),
        lambda r: r['source_hashes'].update({'resources/views/components/ui/footer.blade.php': '0' * 64}),
        lambda r: r['source_hashes'].pop(next(iter(r['source_hashes']))),
        lambda r: r['cases'].pop(),
        lambda r: r['cases'].__setitem__(1, deepcopy(r['cases'][0])),
        lambda r: r['cases'][0].update(passed=False),
        lambda r: r['cases'][0].update(stage='home'),
        lambda r: r['cases'][0].update(failure='injected'),
        lambda r: r['cases'][0].update(page_errors=['injected']),
        lambda r: r['cases'][0].update(mutations=['POST']),
        lambda r: r['cases'][0].update(http=[200, 500, 200, 200]),
        lambda r: r['cases'][0].update(keyboard_home=False),
        lambda r: r['cases'][0].update(pointer_home=False),
        lambda r: r['cases'][0]['checkpoints'].pop('focus'),
        lambda r: r['cases'][0]['screenshots'].pop('home'),
        lambda r: r['cases'][0]['screenshots'].update(login='0' * 64),
        lambda r: r['cases'][0]['checkpoints']['login'].update(texts=[]),
        lambda r: r['cases'][0]['checkpoints']['login']['texts'][0].update(lines=[]),
        lambda r: r['cases'][0]['checkpoints']['login']['texts'][0].update(contrast=1),
        lambda r: r['cases'][0]['checkpoints']['login']['texts'][0].update(contrast=float('nan')),
        lambda r: r['cases'][0]['checkpoints']['login']['link'].update(height=32),
        lambda r: r['cases'][0]['checkpoints']['login']['link'].update(left=-100),
        lambda r: r['cases'][0]['checkpoints']['login']['link'].update(right=9000),
        lambda r: r['cases'][0]['checkpoints']['login']['link'].update(top=-100),
        lambda r: r['cases'][0]['checkpoints']['login']['link'].update(width=float('inf')),
        lambda r: r['cases'][0]['checkpoints']['login'].update(linkOnTop=False),
        lambda r: r['cases'][0]['checkpoints']['focus'].update(focusVisible=False),
        lambda r: r['cases'][0]['checkpoints']['focus'].update(outlineWidth=1),
    ]
    for mutate in mutations:
        copy = deepcopy(report)
        mutate(copy)
        rejected(lambda: validate_report(copy, output))
    scratch = os.environ.get('RUNNER_TEMP') or os.environ.get('TMPDIR')
    assert scratch, 'scratch_required'
    with tempfile.TemporaryDirectory(prefix='regalu-footer-validator-', dir=scratch) as temporary:
        root = Path(temporary)
        build = root / 'build'
        build.mkdir()
        (build / 'asset.js').write_bytes(b'synthetic public asset')
        outside = root / 'outside.txt'
        outside.write_bytes(b'synthetic non-asset; not a credential')
        (build / 'escape.js').symlink_to(outside)
        (build / 'directory').mkdir()
        assert asset_path(build, 'asset.js') == (build / 'asset.js').resolve()
        invalid = ['../outside.txt', '/absolute.js', 'C:/absolute.js', '..\\outside.txt',
                   'escape.js', 'directory', 'missing.js', '', None]
        for name in invalid:
            rejected(lambda name=name: asset_path(build, name))
        source = output / report['cases'][0]['case'] / 'login.png'
        image = source.read_bytes()
        corrupted = [image[:8], image[:-1], image + b'trailing', image[:40] + bytes([image[40] ^ 1]) + image[41:]]
        for index, data in enumerate(corrupted):
            path = root / f'corrupt-{index}.png'
            path.write_bytes(data)
            rejected(lambda path=path: validate_png(path, 320))
        rejected(lambda: validate_png(source, 1280))
    assert args.report.read_bytes() == original_bytes, 'genuine_receipt_changed'
    print(json.dumps({'passed': True, 'report_mutations_rejected': len(mutations),
                      'asset_paths_rejected': len(invalid), 'invalid_pngs_rejected': len(corrupted) + 1,
                      'receipt_sha256': hashlib.sha256(original_bytes).hexdigest()}))


if __name__ == '__main__':
    main()
