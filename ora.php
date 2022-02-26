<?php

function load_config($config_location, $config) 
{    
    if (!is_file($config_location)) {
        die('[ERROR] Config file does not exist, try creating one with `php ora.php config <editor>`' . PHP_EOL);
    }

    $data = json_decode(file_get_contents($config_location), true);

    $keys = array_keys($data);
    $skeleton = array_keys($config);

    foreach ($skeleton as $key) {
        if (!in_array($key, $keys)) {
            die('[ERROR] Config file does not contain ' . $key . PHP_EOL);
        }
    }

    return $data;
}

function create_config($editor, $config_location, $config) 
{
    file_put_contents($config_location, json_encode($config, JSON_PRETTY_PRINT));

    echo '[NOTE] Config file created, openning in ' . $editor;
    sleep(3);
    system($editor . ' ' . $config_location . '> `tty`');
}

function send_slack_message($token, $channel, $text)
{
    $curl = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer' . $token,
            'Content-Type: \'application/json\'',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'channel' => $channel,
            'text' => $text
        ]),
    ]);
    curl_exec($curl);
    curl_close($curl);
}

function check($partition, $max)
{
    exec('df -h', $df);
    foreach ($df as $line) {
        $values = array_filter(explode(' ', $line), fn($item) => $item != '');
        $values = array_values($values);
        if ($values[0] == $partition) {
            if ($values[4] > $max) {
                return $values[4];
            }
        }
    }
    return false;
}

function help()
{
    return 'Help
- config <editor> creates config
- --help - shows this
  -h
';
}

function message($text, $percentage)
{
    return str_replace('<percentage>', $percentage, $text);
}

function main($arguments)
{
    $config = [
        'partition' => '', 
        'max-usage' => 90,  
        
        'slack-channel-name' => '',
        'slack-token' => '',
        'slack-message' => 'Disc usage is <percentage>! <@coolfido>'
    ];

    $config_location = __DIR__ . '/ora.json';
    if (isset($arguments[1])) {
        switch ($arguments[1]) { 
            case '--help':
            case '-h':
                echo help();
                break;
            case 'config':
                create_config(
                    isset($arguments[2]) ? $arguments[2] : 'vim',
                    $config_location,
                    $config
                );
                break;
            default:
                echo '[ERROR] Command not found!' . PHP_EOL;
                help();
                break;
        }
        return;
    }

    $config = load_config($config_location, $config);
    if ($percentage = check($config['partition'], $config['max-usage'])) {
        send_slack_message(
            $config['slack-token'], 
            $config['slack-channel-name'], 
            message($config['slack-message'], $percentage)
        );
    }
}




main($argv);
