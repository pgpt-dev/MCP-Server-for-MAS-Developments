<?php
/**
 * MCPDeleteGroupClient.php
 *
 * A PHP script acting as a Delete Group Client. It connects to a server via TCP,
 * sends a request to delete an existing group, and receives the server's response.
 *
 * Usage:
 * php MCPDeleteGroupClient.php --server-ip <IP> --server-port <Port> --token <Token> --group-name <GroupName>
 */

/**
 * Function to parse command line arguments
 *
 * @param array $args Command line arguments
 * @return array Associative array of parsed arguments
 */
function parseArguments($args) {
    $parsedArgs = [];
    $argc = count($args);
    for ($i = 1; $i < $argc; $i++) {
        switch ($args[$i]) {
            case '--server-ip':
                if (isset($args[$i + 1]) && !startsWith($args[$i + 1], '--')) {
                    $parsedArgs['serverIp'] = $args[++$i];
                } else {
                    fwrite(STDERR, "⚠️ Warning: No value provided for --server-ip.\n");
                }
                break;
            case '--server-port':
                if (isset($args[$i + 1]) && !startsWith($args[$i + 1], '--')) {
                    $parsedArgs['serverPort'] = intval($args[++$i]);
                } else {
                    fwrite(STDERR, "⚠️ Warning: No value provided for --server-port.\n");
                }
                break;
            case '--token':
                if (isset($args[$i + 1]) && !startsWith($args[$i + 1], '--')) {
                    $parsedArgs['token'] = $args[++$i];
                } else {
                    fwrite(STDERR, "⚠️ Warning: No value provided for --token.\n");
                }
                break;
            case '--group-name':
                if (isset($args[$i + 1]) && !startsWith($args[$i + 1], '--')) {
                    $parsedArgs['groupName'] = $args[++$i];
                } else {
                    fwrite(STDERR, "⚠️ Warning: No value provided for --group-name.\n");
                }
                break;
            default:
                fwrite(STDERR, "⚠️ Warning: Unknown argument: {$args[$i]}\n");
        }
    }
    return $parsedArgs;
}

/**
 * Helper function to check if a string starts with a specific prefix
 *
 * @param string $string The string to check
 * @param string $prefix The prefix
 * @return bool True if the string starts with the prefix, otherwise False
 */
function startsWith($string, $prefix) {
    return substr($string, 0, strlen($prefix)) === $prefix;
}

/**
 * Function for interactively prompting a parameter (optional)
 *
 * @param string $prompt The prompt message
 * @return string User input
 */
function askQuestionPrompt($prompt) {
    if (preg_match('/^win/i', PHP_OS)) {
        $vbscript = sys_get_temp_dir() . 'prompt_input.vbs';
        file_put_contents($vbscript, 'wscript.echo(InputBox("' . addslashes($prompt) . '", "", ""))');
        $response = shell_exec("cscript //nologo " . escapeshellarg($vbscript));
        unlink($vbscript);
        return trim($response);
    } else {
        echo $prompt;
        $handle = fopen("php://stdin", "r");
        $response = trim(fgets($handle));
        fclose($handle);
        return $response;
    }
}

/**
 * Function to send a Delete Group request over a TCP connection
 *
 * @param string $serverIp The server's IP address
 * @param int $serverPort The server's port
 * @param string $token The authentication token
 * @param string $groupName The name of the group to be deleted
 * @return array The response received from the server as an associative array
 * @throws Exception On connection errors or JSON parsing errors
 */
function sendDeleteGroupRequest($serverIp, $serverPort, $token, $groupName) {
    $payload = [
        "command" => "delete_group",
        "token" => $token,
        "arguments" => [
            "groupName" => $groupName
        ]
    ];

    $jsonPayload = json_encode($payload);
    if ($jsonPayload === false) {
        throw new Exception("Error while coding the JSON payload: " . json_last_error_msg());
    }

    $errno = 0;
    $errstr = '';
    $timeoutDuration = 10; // Seconds
    $client = @fsockopen($serverIp, $serverPort, $errno, $errstr, $timeoutDuration);

    if (!$client) {
        throw new Exception("Connection error: $errstr ($errno)");
    }

    echo "🔗 Connected to the server ({$serverIp}:{$serverPort}).\n";
    echo "📤 Sending payload: $jsonPayload\n";

    fwrite($client, $jsonPayload);

    $responseData = '';
    stream_set_timeout($client, $timeoutDuration);

    while (!feof($client)) {
        $data = fread($client, 1024);
        if ($data === false) {
            throw new Exception("Error reading data from the server.");
        }
        if ($data === '') {
            break;
        }
        echo "📥 Received data: $data\n";
        $responseData .= $data;

        $parsedData = json_decode($responseData, true);
        if ($parsedData !== null) {
            echo "✅ JSON response successfully parsed.\n";
            fclose($client);
            return $parsedData;
        }

        $info = stream_get_meta_data($client);
        if ($info['timed_out']) {
            throw new Exception("Timeout waiting for data from the server.");
        }
    }

    fclose($client);
    throw new Exception("Connection to the server was closed before a complete response was received.");
}

/**
 * Main function of the script
 */
function main($argv) {
    $parsedArgs = parseArguments($argv);
    $serverIp = $parsedArgs['serverIp'] ?? null;
    $serverPort = $parsedArgs['serverPort'] ?? null;
    $token = $parsedArgs['token'] ?? null;
    $groupName = $parsedArgs['groupName'] ?? null;

    if (!$serverIp) {
        $serverIp = askQuestionPrompt('🔗 Please enter the server IP: ');
    }
    if (!$serverPort) {
        $portInput = askQuestionPrompt('🔗 Please enter the server port: ');
        $serverPort = intval($portInput);
        if ($serverPort <= 0) {
            fwrite(STDERR, "❌ ERROR: Invalid server port.\n");
            exit(1);
        }
    }
    if (!$token) {
        $token = askQuestionPrompt('🔒 Please enter your authentication token: ');
    }
    if (!$groupName) {
        $groupName = askQuestionPrompt('👥 Please enter the group name: ');
    }

    if (!$serverIp || !$serverPort || !$token || !$groupName) {
        fwrite(STDERR, "❌ ERROR: Missing required parameters.\n");
        fwrite(STDOUT, "Usage: php MCPDeleteGroupClient.php --server-ip <IP> --server-port <Port> --token <Token> --group-name <GroupName>\n");
        exit(1);
    }

    try {
        echo "🗑️ Sending Delete Group request...\n";
        $response = sendDeleteGroupRequest($serverIp, $serverPort, $token, $groupName);
        echo "✔️ Server Response:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        fwrite(STDERR, "❌ ERROR: " . $e->getMessage() . "\n");
    }
}

if (version_compare(PHP_VERSION, '7.1.0') < 0) {
    fwrite(STDERR, "❌ ERROR: This script requires PHP version 7.1 or higher.\n");
    exit(1);
}

main($argv);
?>
