<?php

require_once('vendor/autoload.php');

use GuzzleHttp\Client;
use Dotenv\Dotenv;

function getCustomRules($controld_folder_id)
{
    try {
        $client = new Client();
        $custom_rules = $client->get('https://api.controld.com/profiles/' . $_ENV['CONTROLD_PROFILE_ID'] . '/rules/' . $controld_folder_id, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $_ENV['CONTROLD_API_TOKEN'],
            ]
        ]);
        $custom_rules = (string)$custom_rules->getBody();
        $custom_rules = json_decode($custom_rules, true);

        if (isset($custom_rules['body']['rules'])) {
            $custom_rules = $custom_rules['body']['rules'];
        } else {
            $custom_rules = [];
        }

        return $custom_rules;
    } catch (\Exception $e) {
        throw $e;
    }
}

function clearCustomRules($control_folder_id)
{
    try {
        $custom_rules = getCustomRules($control_folder_id);
        $host_names = array_column($custom_rules, 'PK');
        $client = new Client();
        $response = null;

        foreach ($host_names as $host_name) {
            $client = new Client();
            $response = $client->delete('https://api.controld.com/profiles/' . $_ENV['CONTROLD_PROFILE_ID'] . '/rules/' . $host_name, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $_ENV['CONTROLD_API_TOKEN'],
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
            ]);
            $client = null;

            if ($response->getStatusCode() === 200) {
                echo '- '. $host_name . ' deleted' . "\n";
            }

            $response = null;
        }

        return 1;
    } catch (\Exception $e) {
        throw $e;
    }
}

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    $controld_folders = file_get_contents('data/controld_rule_folders.json');
    $controld_folders = json_decode($controld_folders, true);
    
    if (!$controld_folders) {
        throw new \Exception('No rule folders found');
    }

    $custom_rules = [];
    $status = null;

    foreach ($controld_folders as $controld_folder) {
        $status = clearCustomRules($controld_folder['controld_folder_id']);

        if ($status == 1) {
            echo '- '. $controld_folder['name'] . ' folder cleared' . "\n";
        } elseif ($status == 2) {
            echo '- '. $controld_folder['name'] . ' folder already cleared' . "\n";
        } else {
            echo '- '. $controld_folder['name'] . ' folder not cleared' . "\n";
        }

        $latest_custom_rules = [];
        $custom_rules = [];
        $status = null;
    }
} catch (\Exception $e) {
    die($e->getMessage() . "\n" . $e->getTraceAsString());
}
