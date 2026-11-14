"""Local subprocess tests; these do not contact Docker or qualify MySQL."""
from contextlib import contextmanager, redirect_stdout
import copy
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import time
import unittest
from unittest.mock import patch

import event_join_mysql as runner
from event_join_mysql import command


@contextmanager
def synthetic_resources(*, load: float = 0.0, available: float = 4 * 1024**3, free: float = 4 * 1024**3):
    """Only synthetic lifecycle tests use these metrics; no native work runs."""
    read_text = Path.read_text

    def read(path, *args, **kwargs):
        if path == Path('/proc/meminfo'):
            return f'MemAvailable: {int(available // 1024)} kB\n'
        return read_text(path, *args, **kwargs)

    usage = shutil._ntuple_diskusage(total=8 * 1024**3, used=4 * 1024**3, free=int(free))
    with patch.object(runner.os, 'getloadavg', return_value=(load, load, load)), \
            patch.object(runner.shutil, 'disk_usage', return_value=usage), \
            patch.object(Path, 'read_text', read):
        yield


class AdmissionTests(unittest.TestCase):
    def test_resource_refusal_happens_before_external_calls_or_evidence_creation(self):
        for metrics in ({'load': 6.01}, {'available': 2 * 1024**3}, {'free': 2 * 1024**3}):
            with self.subTest(metrics=metrics), tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as directory:
                output = Path(directory) / 'evidence'
                with synthetic_resources(**metrics), \
                        patch.object(sys, 'argv', ['event_join_mysql.py', '--output', str(output)]), \
                        patch.object(runner, 'command') as external:
                    with self.assertRaises(AssertionError):
                        runner.main()
                    external.assert_not_called()
                self.assertFalse(output.exists())


class CommandTests(unittest.TestCase):
    def test_success_and_nonzero_preserve_separate_streams(self):
        result = command([sys.executable, '-c', 'import sys; print("out"); print("err", file=sys.stderr); sys.exit(7)'])
        self.assertEqual((result.returncode, result.stdout, result.stderr), (7, 'out\n', 'err\n'))
        self.assertEqual(command([sys.executable, '-c', 'print("ok")']).stdout, 'ok\n')

    def test_each_stream_and_combined_output_are_bounded(self):
        for script in (
            'import os; os.write(1, b"x" * 4096)',
            'import os; os.write(2, b"x" * 4096)',
            'import os; os.write(1, b"x" * 600); os.write(2, b"x" * 600)',
        ):
            with self.subTest(script=script), self.assertRaisesRegex(RuntimeError, 'output limit'):
                command([sys.executable, '-c', script], max_bytes=1024)

    def test_timeout_reaps_the_real_child(self):
        with tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as directory:
            marker = Path(directory) / 'pid'
            script = 'import os, pathlib, sys, time; pathlib.Path(sys.argv[1]).write_text(str(os.getpid())); time.sleep(30)'
            with self.assertRaisesRegex(RuntimeError, 'deadline'):
                command([sys.executable, '-c', script, str(marker)], timeout=0.5)
            pid = int(marker.read_text())
            self.assertFalse(Path(f'/proc/{pid}').exists(), 'timed-out child was not reaped')

    def test_exited_leader_does_not_orphan_a_pipe_holding_descendant(self):
        with tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as directory:
            marker = Path(directory) / 'late-effect'
            child = 'import pathlib, sys, time; time.sleep(1); pathlib.Path(sys.argv[1]).write_text("unexpected")'
            leader = 'import subprocess, sys; subprocess.Popen([sys.executable, "-c", sys.argv[1], sys.argv[2]])'
            with self.assertRaisesRegex(RuntimeError, 'deadline'):
                command([sys.executable, '-c', leader, child, str(marker)], timeout=0.3)
            time.sleep(1.1)
            self.assertFalse(marker.exists(), 'descendant survived cancellation')


class LifecycleTests(unittest.TestCase):
    """Synthetic Docker/PHP boundary only; real runner, files and cleanup."""

    def exercise(self, fault):
        cid = '2' * 64
        baseline = '1' * 64
        state = {'created': False, 'removed': False, 'starts': 0, 'removals': 0}

        def external(args, **kwargs):
            def result(out='', code=0, err=''):
                return subprocess.CompletedProcess(args, code, out, err)

            if args[0] == 'php':
                return result(json.dumps({'passed': True, 'driver': 'mysql', 'version': '8.0.46',
                    'migrations': len(list((runner.ROOT / 'database/migrations').glob('*.php'))),
                    'source_sha256': hashlib.sha256((runner.ROOT / 'app/Livewire/AmigoSecreto/JoinEvent.php').read_bytes()).hexdigest(),
                    'cases': [{'case': 'invitation', 'passed': True}, {'case': 'code', 'passed': True}]}))
            self.assertEqual(args[0], 'docker')
            if args[1:3] == ['image', 'inspect']:
                return result(json.dumps([{'Os': 'linux', 'Architecture': 'amd64', 'Id': runner.IMAGE.split('@')[1]}]))
            if args[1] == 'ps':
                return result(baseline + ('\n' + cid if state['created'] and not state['removed'] else ''))
            if args[1:3] == ['volume', 'ls']:
                return result('protected-volume')
            if args[1] == 'create':
                state['created'] = True
                name = args[args.index('--name') + 1]
                mount = next(arg for arg in args if arg.startswith('--mount='))
                socket = mount.split('src=')[1].split(',dst=')[0]
                state['fixture'] = Path(socket).parent
                (Path(socket) / 'mysqld.sock').touch()
                state['bound'] = {'Id': cid, 'Image': runner.IMAGE.split('@')[1], 'Name': '/' + name,
                    'Config': {'Image': runner.IMAGE, 'Labels': {'regalu.qualification': name}, 'StopTimeout': 10},
                    'HostConfig': {'NetworkMode': 'none', 'Privileged': False, 'PortBindings': {},
                        'Memory': 1024**3, 'MemorySwap': 1024**3, 'NanoCpus': 10**9, 'PidsLimit': 256,
                        'RestartPolicy': {'Name': 'no'}, 'Tmpfs': {'/var/lib/mysql': 'rw,nosuid,nodev,size=512m'},
                        'LogConfig': {'Type': 'local', 'Config': {'max-size': '10m', 'max-file': '2'}}},
                    'Mounts': [{'Type': 'bind', 'Source': socket, 'Destination': '/var/run/mysqld'}],
                    'State': {'Status': 'running'}}
                if fault == 'create_ack':
                    raise RuntimeError('Synthetic lost create ACK')
                return result(cid)
            if args[1:3] == ['container', 'inspect']:
                if state['removed']:
                    return result('[]', 1, 'Error response from daemon: No such container: ' + cid)
                bound = copy.deepcopy(state['bound'])
                if fault == 'image':
                    bound['Image'] = 'sha256:' + '9' * 64
                elif fault == 'stop_timeout':
                    bound['Config']['StopTimeout'] = 9
                elif fault == 'tmpfs':
                    bound['HostConfig']['Tmpfs'] = {}
                elif fault == 'log_policy':
                    bound['HostConfig']['LogConfig']['Config']['max-file'] = '1'
                return result(json.dumps([bound]))
            if args[1] == 'start':
                state['starts'] += 1
                return result(cid)
            if args[1] == 'exec':
                return result('8.0.46' if 'mysql' in args else 'mysqld')
            if args[1] == 'stop':
                return result(cid)
            if args[1] == 'rm':
                state['removals'] += 1
                state['removed'] = True
                if fault == 'remove_ack':
                    raise RuntimeError('Synthetic lost remove ACK')
                return result(cid)
            self.fail('Unexpected external operation')

        with tempfile.TemporaryDirectory(dir=os.environ.get('TMPDIR')) as directory:
            output = Path(directory) / 'evidence'
            try:
                with synthetic_resources(), \
                        patch.object(sys, 'argv', ['event_join_mysql.py', '--output', str(output)]), \
                        patch.object(runner, 'command', side_effect=external), redirect_stdout(io.StringIO()):
                    code = runner.main()
                report = json.loads((output / 'receipt.json').read_text())
                return code, report, state
            finally:
                # Only synthetic filesystem cleanup; no real Docker resources.
                if state.get('fixture') is not None and state['fixture'].exists():
                    shutil.rmtree(state['fixture'])

    def test_success_validates_the_current_schema_and_removes_the_fixture(self):
        code, report, state = self.exercise(None)
        self.assertEqual(code, 0)
        self.assertTrue(report['passed'])
        self.assertTrue(report['cleanup_verified'])
        self.assertFalse(state['fixture'].exists())
        self.assertEqual((state['starts'], state['removals']), (1, 1))

    def test_lost_create_ack_reconciles_and_removes_once_but_stays_failed(self):
        code, report, state = self.exercise('create_ack')
        self.assertEqual(code, 1)
        self.assertFalse(report['passed'])
        self.assertTrue(report['cleanup_verified'])
        self.assertEqual((state['starts'], state['removals']), (0, 1))

    def test_lost_remove_ack_is_read_back_without_retry_or_false_success(self):
        code, report, state = self.exercise('remove_ack')
        self.assertEqual(code, 1)
        self.assertFalse(report['passed'])
        self.assertTrue(report['cleanup_verified'])
        self.assertEqual(state['removals'], 1)

    def test_native_inspect_policy_drift_prevents_start(self):
        for fault in ('image', 'stop_timeout', 'tmpfs', 'log_policy'):
            with self.subTest(fault=fault):
                code, report, state = self.exercise(fault)
                self.assertEqual(code, 1)
                self.assertFalse(report['passed'])
                self.assertEqual(state['starts'], 0)
                self.assertEqual(state['removals'], 0 if fault == 'image' else 1)


if __name__ == '__main__':
    unittest.main()
