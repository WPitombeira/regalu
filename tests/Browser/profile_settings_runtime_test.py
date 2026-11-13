"""Real PHP HTTP isolation regression, with an injected ambient INI default.

Only command configuration is injected; the seed, server and Laravel bootstrap
are real. No account credentials or environment values are emitted.
"""
import json
from pathlib import Path
import subprocess
import unittest
from unittest.mock import patch
import urllib.request

import profile_settings_smoke as smoke


class ProfileRuntimeTest(unittest.TestCase):
    def test_http_storage_stays_run_owned_with_gpcs_as_the_ambient_default(self):
        original_popen = subprocess.Popen

        def ambient_gpcs(command, *args, **kwargs):
            if command[0] == 'php':
                command = [command[0], '-d', 'variables_order=GPCS', *command[1:]]
            return original_popen(command, *args, **kwargs)

        with patch.object(subprocess, 'Popen', new=ambient_gpcs):
            control = subprocess.run(
                ['php', '-r', 'echo ini_get("variables_order");'],
                capture_output=True, text=True, check=True, timeout=10,
            )
            self.assertEqual(control.stdout, 'GPCS')
            with smoke.application('en') as (origin, _, database):
                directory = database.parent
                probe = directory / 'public' / 'storage-isolation.php'
                probe.write_text(
                    '<?php require ' + json.dumps(str(smoke.ROOT / 'vendor/autoload.php')) + '; '
                    '$app = require ' + json.dumps(str(smoke.ROOT / 'bootstrap/app.php')) + '; '
                    '$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
                    '$expected = getenv("LARAVEL_STORAGE_PATH"); '
                    'header("Content-Type: application/json"); '
                    'echo json_encode(['
                    '"environment_exposed" => isset($_ENV["LARAVEL_STORAGE_PATH"]), '
                    '"storage_owned" => storage_path() === $expected, '
                    '"public_owned" => config("filesystems.disks.public.root") === $expected."/app/public", '
                    '"private_owned" => config("filesystems.disks.local.root") === $expected."/app/private"'
                    ']);'
                )
                with urllib.request.urlopen(origin + '/storage-isolation.php', timeout=10) as response:
                    self.assertEqual(response.status, 200)
                    self.assertEqual(json.load(response), {
                        'environment_exposed': True, 'storage_owned': True,
                        'public_owned': True, 'private_owned': True,
                    })
            self.assertFalse(directory.exists(), 'owned fixture was not removed')


if __name__ == '__main__':
    unittest.main()
