"""Mutation-test the actual event-budget report validator; never replace real browser evidence."""
import argparse
from copy import deepcopy
import json
import os
from pathlib import Path
import tempfile
import unittest

from event_budget_smoke import validate_report
from event_budget_support import asset_path, validate_png


class IntegrityTest(unittest.TestCase):
    output: Path
    report: dict

    def reject(self, mutate, reason):
        report = deepcopy(self.report)
        mutate(report)
        with self.assertRaisesRegex(AssertionError, reason):
            validate_report(report, self.output)

    def test_real_receipt(self):
        validate_report(self.report, self.output)

    def test_duplicate_case(self):
        self.reject(lambda r: r['cases'].__setitem__(1, deepcopy(r['cases'][0])), 'case_matrix')

    def test_incomplete_terminal(self):
        self.reject(lambda r: r['cases'][0].__setitem__('stage', 'upper'), 'case_incomplete')

    def test_missing_geometry(self):
        self.reject(lambda r: r['cases'][0]['checkpoints'].__setitem__('initial', []), 'measurement_count')

    def test_dropped_screenshot(self):
        self.reject(lambda r: r['cases'][0]['screenshots'].pop('initial'), 'checkpoint_set')

    def test_changed_source(self):
        self.reject(lambda r: r['source_hashes'].__setitem__('app/Livewire/AmigoSecreto/CreateEvent.php', '0' * 64), 'source_drift')

    def test_changed_checkout(self):
        self.reject(lambda r: r['checkout'].__setitem__('commit', '0' * 40), 'checkout_drift')

    def test_missing_native_refusal(self):
        self.reject(lambda r: r['cases'][0].__setitem__('native_blocked', []), 'native_or_keyboard_missing')

    def test_missing_persistence(self):
        self.reject(lambda r: r['cases'][0].__setitem__('saved', []), 'saved_cases')

    def test_missing_rejection(self):
        self.reject(lambda r: r['cases'][0].__setitem__('invalid_unchanged', []), 'rejection_missing')

    def test_missing_cleanup(self):
        self.reject(lambda r: r['cases'][0].__setitem__('fixture_cleanup', False), 'fixture_or_page_error')

    def test_bad_http(self):
        self.reject(lambda r: r['cases'][0]['http'].__setitem__(0, 500), 'http_matrix')

    def test_bad_geometry(self):
        for key, value, reason in [('height', 42, 'budget_touch_target'), ('contrast', 1, 'budget_contrast'),
                                   ('text_width', 10000, 'budget_value_clipped'), ('x', -10, 'budget_overflow')]:
            def mutate(r):
                row = next(x for x in r['cases'][0]['checkpoints']['initial'] if x['tag'] == 'INPUT')
                row[key] = value
            with self.subTest(key=key):
                self.reject(mutate, reason)

    def test_png_corruption(self):
        case = self.report['cases'][0]['case']
        genuine = self.output / case / 'initial.png'
        with tempfile.TemporaryDirectory(prefix='budget-png-test-', dir=os.environ.get('RUNNER_TEMP') or os.environ['TMPDIR']) as temporary:
            path = Path(temporary) / 'mutated.png'
            data = genuine.read_bytes()
            for payload in (data[:8], data[:32], data[:-8], data[:48] + bytes([data[48] ^ 1]) + data[49:]):
                path.write_bytes(payload)
                with self.assertRaisesRegex(AssertionError, 'png_'):
                    validate_png(path, 320)
            with self.assertRaisesRegex(AssertionError, 'png_dimensions'):
                validate_png(genuine, 390)

    def test_asset_containment(self):
        with tempfile.TemporaryDirectory(prefix='budget-asset-test-', dir=os.environ.get('RUNNER_TEMP') or os.environ['TMPDIR']) as temporary:
            root = Path(temporary)
            build = root / 'build'
            build.mkdir()
            (build / 'valid.js').write_text('// synthetic')
            outside = root / 'synthetic.js'
            outside.write_text('// synthetic non-secret fixture')
            (build / 'out.js').symlink_to(outside)
            self.assertEqual(asset_path(build, 'valid.js'), build / 'valid.js')
            for name in ('../synthetic.js', str(outside), 'C:/synthetic.js', '..\\synthetic.js', 'out.js'):
                with self.subTest(name=name), self.assertRaisesRegex(AssertionError, 'asset_'):
                    asset_path(build, name)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    IntegrityTest.output = args.output.resolve()
    IntegrityTest.report = json.loads((IntegrityTest.output / 'report.json').read_text())
    unittest.main(argv=[__file__])
