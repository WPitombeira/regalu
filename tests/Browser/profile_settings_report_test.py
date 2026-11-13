"""Qualify the actual report validator using immutable real browser evidence.

Mutations are in-memory negative fixtures, not additional browser executions.
"""
import argparse
from copy import deepcopy
import json
from pathlib import Path

from profile_settings_smoke import matrix, validate


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    path = args.output / 'results.json'
    original = path.read_bytes()
    report = json.loads(original)
    expected = matrix()
    hashes = dict(report['source_hashes'])
    validate(report, args.output, expected, hashes)

    def geometry(candidate):
        return candidate['cases'][0]['geometry']['initial']

    def nojs_geometry(candidate):
        return candidate['cases'][-1]['geometry']['no-javascript']

    def overflow(candidate, javascript=False, source='legacy-account-menu'):
        data = nojs_geometry(candidate)
        data['content'] = data['viewport'] + 100
        data['javascript'] = javascript
        data['externalOverflow'] = [{'source': source, 'right': data['content']}]

    mutations = [
        ('duplicate case', 'case_set', lambda r: r['cases'].__setitem__(1, deepcopy(r['cases'][0]))),
        ('partial terminal stage', 'incomplete_case', lambda r: r['cases'][0].update(stage='started')),
        ('failed case', 'incomplete_case', lambda r: r['cases'][0].update(passed=False)),
        ('page error', 'incomplete_case', lambda r: r['cases'][0]['page_errors'].append('javascript_error')),
        ('source drift', 'source_drift', lambda r: r['source_hashes'].__setitem__(next(iter(hashes)), '0' * 64)),
        ('empty controls', 'empty_measurements', lambda r: geometry(r).update(controls=[])),
        ('empty contrast', 'empty_measurements', lambda r: geometry(r).update(contrast=[])),
        ('dropped checkpoint', 'missing_checkpoint', lambda r: r['cases'][0]['geometry'].pop('initial')),
        ('dropped screenshot', 'missing_screenshot', lambda r: r['cases'][0]['screenshots'].pop()),
        ('low contrast', 'contrast', lambda r: geometry(r)['contrast'].__setitem__(0, 1.0)),
        ('short control', 'touch_target', lambda r: geometry(r)['controls'][0].update(height=20)),
        ('mislabelled JavaScript', 'case_geometry', lambda r: geometry(r).update(javascript=False)),
        ('wrong viewport', 'case_geometry', lambda r: geometry(r).update(viewport=2560)),
        ('JavaScript shell overflow', 'case_geometry', lambda r: overflow(r, javascript=True)),
        ('unrelated no-JS overflow', 'page_overflow', lambda r: overflow(r, source='other')),
        ('profile no-JS overflow', 'control_overflow', lambda r: nojs_geometry(r).update(profileOverflow=[{'left': -20}])),
        ('unattributed no-JS overflow', 'page_overflow', lambda r: (overflow(r), nojs_geometry(r).update(externalOverflow=[]))),
        ('incomplete no-JS attribution', 'page_overflow', lambda r: (overflow(r), nojs_geometry(r)['externalOverflow'][0].update(right=321))),
    ]
    for label, reason, mutate in mutations:
        candidate = deepcopy(report)
        mutate(candidate)
        try:
            validate(candidate, args.output, expected, hashes)
        except AssertionError as error:
            assert str(error) == reason, f'{label}: unexpected rejection reason'
        else:
            raise AssertionError(f'{label}: invalid evidence accepted')

    # The genuine receipt stays untouched; the exception never certifies the legacy menu.
    assert path.read_bytes() == original, 'real receipt was modified'
    validate(report, args.output, expected, hashes)
    print(json.dumps({'validator_passed': True, 'rejected_mutations': len(mutations),
                      'browser_cases': len(report['cases']),
                      'screenshots': sum(len(case['screenshots']) for case in report['cases'])}))


if __name__ == '__main__':
    main()
