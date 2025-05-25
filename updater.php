<?php

require_once('vendor/autoload.php');

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

function getRemoteRules($url)
{
    try {
        $client = new Client();
        $rules = $client->get($url);
        $rules = (string)$rules->getBody();
        $rules = json_decode($rules, true);
        $rules = $rules['rules'];

        return $rules;
    } catch (\Exception $e) {
        throw $e;
    }
}

function getLocalRules($path)
{
    try {
        $rules = file_get_contents($path);
        $rules = json_decode($rules, true);

        if (isset($rules['rules'])) {
            $rules = $rules['rules'];
        } else {
            $rules = [];
        }

        return $rules;
    } catch (\Exception $e) {
        throw $e;
    }
}

function cleanRuleNames($data)
{
    $names = array_unique($data);
    natsort($names);

    return array_values($names);
}

function getCurrentRules($controld_folder_id, $endpoint_type)
{
    try {
        $client = new Client();
        $rules = $client->get('https://api.controld.com/profiles/' . $_ENV['CONTROLD_' . strtoupper($endpoint_type) . '_PROFILE_ID'] . '/rules/' . $controld_folder_id, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $_ENV['CONTROLD_API_TOKEN'],
            ]
        ]);
        $rules = (string)$rules->getBody();
        $rules = json_decode($rules, true);

        if (isset($rules['body']['rules'])) {
            $rules = $rules['body']['rules'];
        } else {
            $rules = [];
        }

        return $rules;
    } catch (RequestException $re) {
        if ($re->hasResponse()){
            if ($re->getResponse()->getStatusCode() === 400) {
                throw new Exception((string)$re->getResponse()->getBody());
            }
        }
    } catch (\Exception $e) {
        throw $e;
    }
}

function importRules($names, $current_names, $controld_folder_id, $rule_type, $endpoint_type)
{
    $new_names = array_diff($names, $current_names);

    if (!$new_names) {
        return true;
    }

    $new_names = cleanRuleNames($new_names);
    $new_name_chunks = array_chunk($new_names, 990);
    $form_params = [];
    $chunk = [];
    $client = null;

    foreach ($new_name_chunks as $new_name_chunk) {
        $chunk = array_values($new_name_chunk);
        $form_params = [
            'do' => $rule_type,
            'status' => 1,
            'group' => $controld_folder_id,
        ];
    
        foreach ($chunk as $key => $value) {
            $form_params['hostnames[' . $key . ']'] = $value;
        }
    
        try {
            $client = new Client();
            $client->post('https://api.controld.com/profiles/' . $_ENV['CONTROLD_' . strtoupper($endpoint_type) . '_PROFILE_ID'] . '/rules', [
                'form_params' => $form_params,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $_ENV['CONTROLD_API_TOKEN'],
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
            ]);
        } catch (RequestException $re) {
            if ($re->hasResponse()){
                if ($re->getResponse()->getStatusCode() === 400) {
                    throw new Exception((string)$re->getResponse()->getBody());
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }

        $chunk = [];
        $client = null;
        $form_params = [];
        sleep(2);
    }

    return true;
}

$endpoint = (int)readline('Enter target endpoint: ');
$endpoint_type = '';
$profiled_id = '';

if ($endpoint == 1) {
    $endpoint_type = 'home';
    $profiled_id = '526558xsp0b5';
} elseif ($endpoint == 2) {
    $endpoint_type = 'office';
    $profiled_id = '684852xsp7gy';
}

if (!$endpoint_type) {
    die('Invalid endpoint');
}

$folders = file_get_contents('data/data.json');
$folders = json_decode($folders, true);

$folders = $folders[$endpoint_type];
$allows = $folders['allows'];
$remote_rules = [];
$local_rules = [];
$rules = [];
$allow_names = [];

foreach ($allows as $allow) {
    if ($allow['url'] && !$allow['local_path']) {
        $rules = getRemoteRules($allow['url']);
    } elseif (!$allow['url'] && $allow['local_path']) {
        $rules = getLocalRules($allow['local_path']);
    } elseif ($allow['url'] && $allow['local_path']) {
        $remote_rules = getRemoteRules($allow['url']);
        $local_rules = getLocalRules($allow['local_path']);
        $rules = array_merge($remote_rules, $local_rules);
    }

    $rules = array_column($rules, 'PK');
    $allow_names = array_merge($allow_names, $rules);
    $rules = [];
    sleep(2);
}

$allow_names = cleanRuleNames($allow_names);
$blocks = $folders['blocks'];
$remote_rules = [];
$local_rules = [];
$rules = [];
$block_names = [];

foreach ($blocks as $block) {
    if ($block['url'] && !$block['local_path']) {
        $rules = getRemoteRules($block['url']);
    } elseif (!$block['url'] && $block['local_path']) {
        $rules = getLocalRules($block['local_path']);
    } elseif ($block['url'] && $block['local_path']) {
        $remote_rules = getRemoteRules($block['url']);
        $local_rules = getLocalRules($block['local_path']);
        $rules = array_merge($remote_rules, $local_rules);
    }

    $rules = array_column($rules, 'PK');
    $block_names = array_merge($block_names, $rules);
    $rules = [];
    sleep(2);
}

$block_names = cleanRuleNames($block_names);

foreach ($block_names as $key => $value) {
    if (in_array($value, $allow_names)) {
        unset($block_names[$key]);
    }
}

$block_names = cleanRuleNames($block_names);
$current_rules = getCurrentRules(1, $endpoint_type);

$current_rule_names = array_column($current_rules, 'PK');
$status = importRules($allow_names, $current_rule_names, 1, 1, $endpoint_type);

$current_rule_names = [];
$status = null;
$current_rules = getCurrentRules(2, $endpoint_type);

$current_rule_names = array_column($current_rules, 'PK');
$status = importRules($block_names, $current_rule_names, 2, 0, $endpoint_type);

$current_rule_names = [];
$status = null;
