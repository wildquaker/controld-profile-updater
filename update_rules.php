<?php

require_once('vendor/autoload.php');

use GuzzleHttp\Client;
use Dotenv\Dotenv;

function getLatestCustomRules($url)
{
    try {
        $client = new Client();
        $latest_custom_rules = $client->get($url);
        $latest_custom_rules = (string)$latest_custom_rules->getBody();
        $latest_custom_rules = json_decode($latest_custom_rules, true);
        $latest_custom_rules = $latest_custom_rules['rules'];

        return $latest_custom_rules;
    } catch (\Exception $e) {
        throw $e;
    }
}

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

function getLocalSourceRules($local_source_path)
{
    try {
        $custom_rules = file_get_contents($local_source_path);
        $custom_rules = json_decode($custom_rules, true);

        if (isset($custom_rules['rules'])) {
            $custom_rules = $custom_rules['rules'];
        } else {
            $custom_rules = [];
        }

        return $custom_rules;
    } catch (\Exception $e) {
        throw $e;
    }
}

function updateCustomRules($controld_folder_id, $latest_custom_rules, $custom_rules, $rule_type)
{
    $latest_host_names = array_column($latest_custom_rules, 'PK');
    $host_names = array_column($custom_rules, 'PK');
    $new_host_names = array_diff($latest_host_names, $host_names);

    if (!count($new_host_names)) {
        return 2;
    }

    $new_host_names = array_values($new_host_names);
    $form_params = [
        'do' => $rule_type,
        'status' => 1,
        'group' => $controld_folder_id,
    ];
    /*$skips = [
        'bat.bing.com',
        'bs.serving-sys.com',
        'c.msn.com',
        'c1.microsoft.com',
        'da.xboxservices.com',
        'go.microsoft.com',
        'purchase.mp.microsoft.com',
        'searx.work',
        'sentry.*',
        'store-images.s-microsoft.com',
        'xo.wtf',
    ];*/

    $skips = [
        'bat.bing.com',
        'bs.serving-sys.com',
        'c1.microsoft.com',
        'searx.work',
        'xo.wtf',
    ];

    foreach ($new_host_names as $key => $value) {
        if (in_array($value, $skips)) {
            continue;
        }

        $form_params['hostnames[' . $key . ']'] = $value;
    }

    try {
        $client = new Client();
        $response = $client->post('https://api.controld.com/profiles/' . $_ENV['CONTROLD_PROFILE_ID'] . '/rules', [
            'form_params' => $form_params,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $_ENV['CONTROLD_API_TOKEN'],
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);

        return ($response->getStatusCode() === 200) ? 1 : 0;
    } catch (\Exception $e) {
        throw $e;
    }
}

function updateCustomRules_v2($controld_folder_id, $latest_custom_rules, $custom_rules, $rule_type)
{
    $latest_host_names = array_column($latest_custom_rules, 'PK');
    $host_names = array_column($custom_rules, 'PK');
    $new_host_names = array_diff($latest_host_names, $host_names);

    if (!count($new_host_names)) {
        return 2;
    }

    try {
        /*$skips = [
            'bat.bing.com',
            'bs.serving-sys.com',
            'c.msn.com',
            'c1.microsoft.com',
            'da.xboxservices.com',
            'go.microsoft.com',
            'purchase.mp.microsoft.com',
            'searx.work',
            'sentry.*',
            'store-images.s-microsoft.com',
            'xo.wtf',
        ];*/

        $skips = [
            'bat.bing.com',
            'bs.serving-sys.com',
            'c1.microsoft.com',
            'searx.work',
            'xo.wtf',
        ];

        foreach ($latest_custom_rules as $key => $value) {
            if (in_array($value['PK'], $new_host_names)) {      
                if (in_array($value['PK'], $skips)) {
                    continue;
                }

                $client = new Client();
                $response = $client->post('https://api.controld.com/profiles/' . $_ENV['CONTROLD_PROFILE_ID'] . '/rules', [
                    'form_params' => [
                        'do' => $value['action']['do'],
                        'status' => isset($value['action']['status']) ? $value['action']['status'] : 1,
                        'group' => $controld_folder_id,
                        'hostnames[]' => $value['PK'],
                    ],
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $_ENV['CONTROLD_API_TOKEN'],
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ]
                ]);

                $client = null;

                if ($response->getStatusCode() === 200) {
                    echo '- '. $value['PK'] . ' added' . "\n";
                }
            }
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

    $latest_custom_rules = [];
    $custom_rules = [];
    $status = null;

    foreach ($controld_folders as $controld_folder) {
        if (!$controld_folder['enabled']) {
            continue;
        }

        if ($controld_folder['raw_url'] && !$controld_folder['local_source_path']) {
            $latest_custom_rules = getLatestCustomRules($controld_folder['raw_url']);
            $custom_rules = getCustomRules($controld_folder['controld_folder_id']);
        } elseif (!$controld_folder['raw_url'] && $controld_folder['local_source_path']) {
            $latest_custom_rules = getLocalSourceRules($controld_folder['local_source_path']);
            $custom_rules = getCustomRules($controld_folder['controld_folder_id']);
        }

        $custom_rules = getCustomRules($controld_folder['controld_folder_id']);
        $status = updateCustomRules_v2($controld_folder['controld_folder_id'], $latest_custom_rules, $custom_rules, $controld_folder['rule_type']);
        //$status = updateCustomRules($controld_folder['controld_folder_id'], $latest_custom_rules, $custom_rules, $controld_folder['rule_type']);

        if ($status == 1) {
            echo '- '. $controld_folder['name'] . ' folder updated' . "\n";
        } elseif ($status == 2) {
            echo '- '. $controld_folder['name'] . ' folder already updated' . "\n";
        } else {
            echo '- '. $controld_folder['name'] . ' folder not updated' . "\n";
        }

        $latest_custom_rules = [];
        $custom_rules = [];
        $status = null;
    }
} catch (\Exception $e) {
    die($e->getMessage() . "\n" . $e->getTraceAsString());
}
