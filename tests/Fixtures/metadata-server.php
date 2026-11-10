<?php

// One-shot loopback fixture for real cURL transport tests. Never used by the app.
$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($server === false) {
    exit(1);
}
$address = stream_socket_get_name($server, false);
$port = (int) substr(strrchr($address, ':'), 1);
fwrite(STDOUT, json_encode(['port' => $port], JSON_THROW_ON_ERROR)."\n");
fflush(STDOUT);
$connection = stream_socket_accept($server, 10);
if ($connection === false) {
    fclose($server);
    exit(2);
}
stream_set_timeout($connection, 10);
stream_set_write_buffer($connection, 0);
$request = '';
while (($line = fgets($connection)) !== false && trim($line) !== '') {
    $request .= $line;
}
$scenario = $argv[1];
$written = 0;
$requests = 1;
if ($scenario === 'oversized') {
    fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nTransfer-Encoding: chunked\r\nConnection: close\r\n\r\n");
    $chunk = dechex(8192)."\r\n".str_repeat('x', 8192)."\r\n";
    for ($i = 0; $i < 1024; $i++) {
        $bytes = @fwrite($connection, $chunk);
        if ($bytes === false || $bytes === 0) {
            break;
        }
        $written += $bytes;
    }
    @fwrite($connection, "0\r\n\r\n");
} elseif ($scenario === 'redirect') {
    fwrite($connection, "HTTP/1.1 302 Found\r\nLocation: http://127.0.0.1:{$port}/redirected\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
} else {
    $body = '<html><head><title>Native transport</title></head></html>';
    fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body);
}
fclose($connection);
if ($scenario === 'redirect') {
    $followUp = @stream_socket_accept($server, 1);
    if ($followUp !== false) {
        $requests++;
        $body = '<title>Redirected</title>';
        fwrite($followUp, "HTTP/1.1 200 OK\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body);
        fclose($followUp);
    }
}
fclose($server);
fwrite(STDOUT, json_encode(['request' => $request, 'written' => $written, 'requests' => $requests], JSON_THROW_ON_ERROR)."\n");
