"""Bounded native MySQL probe; no network, shared DB or application env file."""
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import selectors
import shutil
import signal
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
IMAGE = 'mysql@sha256:0845b57a4927c2e2e442b0c3c154ebf2bdede7d6e1cb3b5a4b3e8bc1d0e69e22'


def command(args, timeout: float = 30, max_bytes=1024 * 1024, **kwargs):
    """Bound both streams together; cancel the owned group before reaping."""
    process = subprocess.Popen(args, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                               stderr=subprocess.PIPE, start_new_session=True, **kwargs)
    output = [bytearray(), bytearray()]
    deadline = time.monotonic() + timeout
    try:
        with selectors.DefaultSelector() as selector:
            for index, stream in enumerate((process.stdout, process.stderr)):
                assert stream is not None
                selector.register(stream, selectors.EVENT_READ, index)
            while selector.get_map():
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    raise RuntimeError('Subprocess deadline exceeded')
                for key, _ in selector.select(remaining):
                    chunk = os.read(key.fd, 65536)
                    if not chunk:
                        selector.unregister(key.fileobj)
                        continue
                    if sum(map(len, output)) + len(chunk) > max_bytes:
                        raise RuntimeError('Subprocess output limit exceeded')
                    output[key.data].extend(chunk)
        try:
            code = process.wait(timeout=max(0, deadline - time.monotonic()))
        except subprocess.TimeoutExpired:
            raise RuntimeError('Subprocess deadline exceeded') from None
    except BaseException:
        # Do not poll/wait first: an exited leader can still own live children.
        try:
            os.killpg(process.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
        process.wait()
        raise
    finally:
        for stream in (process.stdout, process.stderr):
            if stream is not None:
                stream.close()
    return subprocess.CompletedProcess(args, code, *(part.decode('utf-8', errors='replace') for part in output))


def docker(*args):
    result = command(['docker', *args])
    if result.returncode:
        raise RuntimeError('Docker operation failed: ' + args[0])
    return result.stdout.strip()


def owned_container(identity, name, image_id, baseline):
    bound = json.loads(docker('container', 'inspect', identity))[0]
    assert re.fullmatch(r'[0-9a-f]{64}', bound['Id']) and bound['Id'] not in baseline
    assert bound['Name'] == '/' + name and bound['Image'] == image_id
    assert bound['Config']['Image'] == IMAGE and bound['Config']['Labels'].get('regalu.qualification') == name
    if identity != name:
        assert bound['Id'] == identity
    return bound


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', required=True)
    parser.add_argument('--extension-dir', type=Path, help='Optional task-local PHP mysqlnd/pdo_mysql module directory')
    args = parser.parse_args()
    destination = Path(args.output).resolve()
    assert not destination.exists()
    script = ROOT / 'tests/Tooling/event_join_mysql.php'
    scratch = Path(os.environ.get('TMPDIR', tempfile.gettempdir())).resolve()
    assert scratch.is_dir()
    available = int(next(line.split()[1] for line in Path('/proc/meminfo').read_text().splitlines() if line.startswith('MemAvailable:'))) * 1024
    assert available >= 3 * 1024**3 and os.getloadavg()[0] <= 6.0
    assert shutil.disk_usage(scratch).free >= 3 * 1024**3
    destination.mkdir(mode=0o700, parents=True)
    image = json.loads(docker('image', 'inspect', IMAGE))[0]
    assert image['Os'] == 'linux' and image['Architecture'] == 'amd64'
    before = set(docker('ps', '-aq', '--no-trunc').splitlines())
    assert len(before) <= 256 and all(re.fullmatch(r'[0-9a-f]{64}', item) for item in before)
    volumes = set(docker('volume', 'ls', '-q').splitlines())
    name = 'regalu-join-mysql-' + secrets.token_hex(8)
    label = 'regalu.qualification=' + name
    receipt = {'image': IMAGE, 'image_id': image['Id'], 'name': name,
               'probe_sha256': hashlib.sha256(script.read_bytes()).hexdigest(),
               'runner_sha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(), 'passed': False}
    (destination / 'create-intent.json').write_text(json.dumps(receipt, indent=2) + '\n')
    cid = None
    result = None
    directory = None
    attempted = False
    fixture = tempfile.mkdtemp(prefix='regalu-join-mysql-', dir=scratch)
    try:
        # A host Unix socket path must fit sockaddr_un, including its terminator.
        # Cleanup stays outside the body, after attributed container removal.
        try:
            temporary = fixture
            directory = Path(temporary)
            assert len(str(directory / 'socket/mysqld.sock').encode()) < 108
            for path in ('socket', 'storage/app/private', 'storage/app/public', 'storage/framework/cache', 'storage/framework/sessions', 'storage/logs', 'views'):
                (directory / path).mkdir(parents=True, exist_ok=True)
            (directory / 'socket').chmod(0o777)
            (directory / '.env').write_text('# Owned fixture; settings are process-only.\n')
            (directory / '.env').chmod(0o600)
            create = ['docker', 'create', '--name', name, '--label', label, '--pull=never',
                      '--network=none', '--memory=1g', '--memory-swap=1g', '--cpus=1', '--pids-limit=256',
                      '--restart=no', '--stop-timeout=10', '--log-driver=local', '--log-opt=max-size=10m', '--log-opt=max-file=2',
                      '--tmpfs=/var/lib/mysql:rw,nosuid,nodev,size=512m',
                      '--mount=type=bind,src=' + str(directory / 'socket') + ',dst=/var/run/mysqld',
                      '-e', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '-e', 'MYSQL_DATABASE=regalu_join_probe', IMAGE,
                      '--skip-networking', '--skip-log-bin', '--mysqlx=0', '--innodb-buffer-pool-size=64M',
                      '--innodb-redo-log-capacity=32M', '--innodb-log-buffer-size=8M', '--max-connections=20']
            attempted = True
            created = command(create)
            # Reconcile name even if create acknowledgement was lost.
            inspected = owned_container(name, name, image['Id'], before)
            cid = inspected['Id']
            assert created.returncode == 0 and created.stdout.strip() == cid
            host = inspected['HostConfig']
            assert host['NetworkMode'] == 'none' and not host['Privileged'] and not host['PortBindings']
            assert host['Memory'] == 1024**3 and host['MemorySwap'] == 1024**3 and host['NanoCpus'] == 10**9
            assert host['PidsLimit'] == 256 and host['RestartPolicy']['Name'] == 'no'
            assert host['Tmpfs'] == {'/var/lib/mysql': 'rw,nosuid,nodev,size=512m'}
            assert inspected['Config']['StopTimeout'] == 10
            assert host['LogConfig'] == {'Type': 'local', 'Config': {'max-size': '10m', 'max-file': '2'}}
            mounts = inspected['Mounts']
            assert len(mounts) == 1 and mounts[0]['Type'] == 'bind'
            assert mounts[0]['Source'] == str(directory / 'socket') and mounts[0]['Destination'] == '/var/run/mysqld'
            assert not host.get('VolumesFrom') and not host.get('Binds')
            receipt.update(container=cid, pre_start_isolation=True)
            (destination / 'identity.json').write_text(json.dumps(receipt, indent=2) + '\n')
            docker('start', cid)
            deadline = time.monotonic() + 90
            while time.monotonic() < deadline:
                ready = command(['docker', 'exec', cid, 'mysql', '--protocol=socket', '-uroot', '-NBe', 'select version()'], timeout=10)
                primary = command(['docker', 'exec', cid, 'sh', '-c', 'read -r comm < /proc/1/comm; printf "%s" "$comm"'], timeout=10)
                receipt['readiness'] = {'mysql_exit': ready.returncode, 'primary_exit': primary.returncode,
                                        'primary': primary.stdout.strip(), 'socket_exists': (directory / 'socket/mysqld.sock').exists()}
                if ready.returncode == 0 and primary.stdout.strip() == 'mysqld' and (directory / 'socket/mysqld.sock').exists():
                    break
                time.sleep(0.5)
            else:
                raise RuntimeError('Owned MySQL readiness deadline exceeded')
            env = {key: os.environ[key] for key in ('PATH', 'HOME', 'PHPRC', 'PHP_INI_SCAN_DIR') if key in os.environ}
            env.update(APP_ENV='qualification', APP_DEBUG='false', APP_URL='http://127.0.0.1',
                       APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                       DB_CONNECTION='mysql', DB_URL='', DB_HOST='127.0.0.1', DB_DATABASE='regalu_join_probe',
                       DB_USERNAME='root', DB_PASSWORD='', DB_SOCKET=str(directory / 'socket/mysqld.sock'),
                       CACHE_STORE='array', CACHE_LIMITER='array', MAIL_MAILER='array', SESSION_DRIVER='array', QUEUE_CONNECTION='sync',
                       BCRYPT_ROUNDS='4', FILESYSTEM_DISK='local', LOG_CHANNEL='null', TMPDIR=temporary,
                       REGALU_MYSQL_DIRECTORY=temporary, LARAVEL_STORAGE_PATH=str(directory / 'storage'),
                       VIEW_COMPILED_PATH=str(directory / 'views'), APP_CONFIG_CACHE=str(directory / 'config.php'),
                       APP_ROUTES_CACHE=str(directory / 'routes.php'), APP_SERVICES_CACHE=str(directory / 'services.php'),
                       APP_PACKAGES_CACHE=str(directory / 'packages.php'))
            php = ['php', '-d', 'variables_order=EGPCS']
            if args.extension_dir is not None:
                extensions = args.extension_dir.resolve()
                for module in ('mysqlnd.so', 'pdo_mysql.so'):
                    assert (extensions / module).is_file()
                    php.extend(['-d', 'extension=' + str(extensions / module)])
            result = command([*php, str(script)], timeout=120, cwd=ROOT, env=env)
            # The probe reports only synthetic case metadata/SQL, never account secrets.
            (destination / 'stdout.txt').write_text(result.stdout)
            (destination / 'stderr.txt').write_text(result.stderr)
            receipt['exit'] = result.returncode
            try:
                receipt['result'] = json.loads(result.stdout)
            except ValueError:
                receipt['result'] = None
            observed = receipt['result']
            receipt['passed'] = result.returncode == 0 and type(observed) is dict and observed.get('passed') is True
            if receipt['passed']:
                assert isinstance(observed, dict)
                assert observed['driver'] == 'mysql' and observed['version'] == '8.0.46'
                assert observed['migrations'] == len(list((ROOT / 'database/migrations').glob('*.php')))
                assert observed['source_sha256'] == hashlib.sha256((ROOT / 'app/Livewire/AmigoSecreto/JoinEvent.php').read_bytes()).hexdigest()
                assert observed['cases'] == [{'case': 'invitation', 'passed': True}, {'case': 'code', 'passed': True}]
            # Stop before tempfile cleanup so the owned socket is no longer live.
            receipt['state_before_cleanup'] = json.loads(docker('container', 'inspect', cid))[0]['State']['Status']
            docker('stop', '--time', '10', cid)
        except Exception as error:
            receipt.update(passed=False, operation_error=type(error).__name__)
    finally:
        receipt['cleanup_verified'] = not attempted
        try:
            if attempted:
                # A timeout is UNKNOWN, not proof create had no effect. Never retry.
                bound = owned_container(cid or name, name, image['Id'], before)
                cid = bound['Id']
                receipt['container'] = cid
                (destination / 'remove-intent.json').write_text(json.dumps({'container': cid, 'name': name}) + '\n')
                try:
                    removed = command(['docker', 'rm', '-f', cid])
                    receipt['remove_exit'] = removed.returncode
                    if removed.returncode != 0:
                        receipt['passed'] = False
                except Exception as error:
                    receipt.update(passed=False, remove_error=type(error).__name__)
                # Independent typed absence readback even after a lost removal ACK.
                absent = command(['docker', 'container', 'inspect', cid])
                receipt['cleanup_verified'] = absent.returncode == 1 and absent.stdout.strip() == '[]' and bool(re.fullmatch(r'(?:Error response from daemon: |Error: )No such container: ' + re.escape(cid), absent.stderr.strip()))
        except Exception as error:
            receipt.update(passed=False, cleanup_error=type(error).__name__)
        try:
            receipt['protected_containers_unchanged'] = set(docker('ps', '-aq', '--no-trunc').splitlines()) == before
            receipt['volumes_unchanged'] = set(docker('volume', 'ls', '-q').splitlines()) == volumes
            if receipt['cleanup_verified']:
                shutil.rmtree(fixture)
            else:
                receipt['retained_fixture'] = fixture
            receipt['fixture_removed'] = not Path(fixture).exists()
        except Exception as error:
            receipt.update(passed=False, postcheck_error=type(error).__name__)
        receipt['passed'] = receipt['passed'] and all(receipt.get(key) is True for key in (
            'cleanup_verified', 'protected_containers_unchanged', 'volumes_unchanged', 'fixture_removed'))
        (destination / 'receipt.json').write_text(json.dumps(receipt, indent=2) + '\n')
    print(json.dumps(receipt, indent=2))
    return 0 if receipt['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
