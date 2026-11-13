"""Negative receipt checks and real HTTP isolation; never creates passing browser evidence."""
import argparse
import copy
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

import avatar_storage_smoke as smoke

REPORT = None
OUTPUT = None


class AvatarRuntimeTest(unittest.TestCase):
    def test_http_storage_and_environment_are_isolated_under_ambient_gpcs(self):
        original = subprocess.Popen
        commands = []

        def gpcs(command, *args, **kwargs):
            if command[0] == 'php':
                command = ['php', '-d', 'variables_order=GPCS', *command[1:]]
                if 'env' in kwargs:
                    self.assertNotIn('REGALU_PARENT_SENTINEL', kwargs['env'])
                    commands.append(command)
            return original(command, *args, **kwargs)

        with patch.dict(smoke.os.environ, {'REGALU_PARENT_SENTINEL': 'must not reach child'}):
            with patch.object(subprocess, 'Popen', new=gpcs):
                control = subprocess.run(['php', '-r', 'echo ini_get("variables_order");'],
                                         capture_output=True, text=True, check=True, timeout=10)
                self.assertEqual(control.stdout, 'GPCS')
                with smoke.application('en') as (_, _, database):
                    directory = database.parent
                    self.assertTrue((directory / 'storage/app/public/avatars').is_file())
                self.assertFalse(directory.exists())
        self.assertEqual(len(commands), 2)
        self.assertTrue(all(command.index('variables_order=GPCS') < command.index('variables_order=EGPCS') for command in commands))

    def test_png_validation_rejects_signature_only_and_corrupt_payload(self):
        with tempfile.TemporaryDirectory(dir=smoke.os.environ.get('TMPDIR') or smoke.os.environ.get('RUNNER_TEMP')) as temporary:
            path = Path(temporary) / 'broken.png'
            for raw in (b'\x89PNG\r\n\x1a\n', smoke.PNG[:-4], smoke.PNG[:40] + b'bad checksum' + smoke.PNG[40:]):
                path.write_bytes(raw)
                with self.assertRaises((AssertionError, ValueError)):
                    smoke.validate_png(path)


class AvatarReceiptTest(unittest.TestCase):
    def test_real_report_and_negative_mutations(self):
        assert isinstance(REPORT, dict) and isinstance(OUTPUT, Path), 'Run receipt qualification with --report after the smoke.'
        hashes = smoke.provenance()
        smoke.validate_report(REPORT, OUTPUT, hashes)
        mutations = [
            lambda data: data['cases'].append(copy.deepcopy(data['cases'][0])),
            lambda data: data['cases'].pop(),
            lambda data: data['cases'][0].update(key='unexpected'),
            lambda data: data['cases'][0].update(stage='started'),
            lambda data: data['cases'][0].update(passed=False),
            lambda data: data['cases'][0].update(errors=['javascript']),
            lambda data: data['source_hashes'].update({'invented': '0' * 64}),
            lambda data: data['cases'][0]['checkpoints'].pop(),
            lambda data: data['cases'][0]['geometry']['refused'].update(controls=[]),
            lambda data: data['cases'][0]['geometry']['refused'].update(content=100000),
            lambda data: data['cases'][0]['geometry']['refused']['controls'][0].update(left=-20),
        ]
        for number, mutate in enumerate(mutations):
            with self.subTest(mutation=number):
                data = copy.deepcopy(REPORT)
                mutate(data)
                with self.assertRaises(AssertionError):
                    smoke.validate_report(data, OUTPUT, hashes)
        # No writes to genuine evidence: redirect only the selected read in memory.
        target = OUTPUT / REPORT['cases'][0]['key'] / 'refused.png'
        original_is_file = Path.is_file
        original_read = Path.read_bytes
        with patch.object(Path, 'is_file', lambda path: False if path == target else original_is_file(path)):
            with self.assertRaises(AssertionError):
                smoke.validate_report(REPORT, OUTPUT, hashes)
        with patch.object(Path, 'read_bytes', lambda path: b'\x89PNG\r\n\x1a\n' if path == target else original_read(path)):
            with self.assertRaises(AssertionError):
                smoke.validate_report(REPORT, OUTPUT, hashes)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    if args.report:
        OUTPUT = args.report.parent
        REPORT = json.loads(args.report.read_text())
        suite = unittest.defaultTestLoader.loadTestsFromTestCase(AvatarReceiptTest)
    else:
        suite = unittest.defaultTestLoader.loadTestsFromTestCase(AvatarRuntimeTest)
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    raise SystemExit(0 if result.wasSuccessful() else 1)
