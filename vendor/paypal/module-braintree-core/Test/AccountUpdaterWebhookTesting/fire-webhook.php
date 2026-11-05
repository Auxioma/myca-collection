<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

// phpcs:ignoreFile

declare(strict_types=1);

use Braintree\Digest;
use Braintree\Gateway;
use Braintree\WebhookNotification;

require 'vendor/autoload.php';

/**
 * @SuppressWarnings(PHPMD)
 */
class CustomWebhookTestingGateway
{
    private $config;

    // phpcs:ignore PEAR.Commenting.FunctionComment.Missing
    public function __construct($gateway)
    {
        $this->config = $gateway->config;
        $this->config->assertHasAccessTokenOrKeys();
    }

    /**
     * Build a sample Webhook
     *
     * @param string $kind the kind of Webhook you want to generate
     * @param string $url
     * @param string $sourceMerchantId optional
     *
     * @return Webhook
     */
    public function sampleNotification($kind, $url, $sourceMerchantId = null)
    {
        $xml = self::_sampleXml($kind, $url, $sourceMerchantId);
        $payload = base64_encode($xml) . "\n";
        $publicKey = $this->config->getPublicKey();
        $sha = Digest::hexDigestSha1($this->config->getPrivateKey(), $payload);
        $signature = $publicKey . "|" . $sha;

        return [
            'bt_signature' => $signature,
            'bt_payload' => $payload
        ];
    }

    private static function _sampleXml($kind, $url, $sourceMerchantId)
    {
        $subjectXml = self::_accountUpdaterDailyReportSampleXml($url);
        $timestamp = self::_timestamp();

        $sourceMerchantIdXml = '';
        if (!is_null($sourceMerchantId)) {
            $sourceMerchantIdXml = "<source-merchant-id>{$sourceMerchantId}</source-merchant-id>";
        }

        return "
        <notification>
            <timestamp type=\"datetime\">{$timestamp}</timestamp>
            <kind>{$kind}</kind>
            {$sourceMerchantIdXml}
            <subject>{$subjectXml}</subject>
        </notification>
        ";
    }

    /**
     * Account updater daily report sample XML
     *
     * @param $url
     * @return string
     */
    private static function _accountUpdaterDailyReportSampleXml($url): string
    {
        return "
        <account-updater-daily-report>
            <report-date type=\"date\">2016-01-14</report-date>
            <report-url>$url</report-url>
        </account-updater-daily-report>
        ";
    }

    /**
     * Timestamp
     *
     * @return string
     */
    private static function _timestamp(): string
    {
        $originalZone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $timestamp = date("Y-m-d\TH:i:s\Z", time());
        date_default_timezone_set($originalZone);

        return $timestamp;
    }
}

/**
 * Update the values below
 */
$environment = 'sandbox';
$merchantId = '';
$publicKey = '';
$privateKey = '';
$webhookUrl = 'https://your-url.test/braintree/webhook/accountUpdater';
$csvUrl = '';

$gateway = new Gateway([
    'environment' => $environment,
    'merchantId' => $merchantId,
    'publicKey' => $publicKey,
    'privateKey' => $privateKey,
]);

$testing = new \CustomWebhookTestingGateway($gateway);
$result = $testing->sampleNotification(WebhookNotification::ACCOUNT_UPDATER_DAILY_REPORT, $csvUrl);

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $webhookUrl,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => http_build_query($result, '', '&'),
]);
$response = curl_exec($curl);

echo 'HTTP response code: ' . curl_getinfo($curl, CURLINFO_HTTP_CODE) . PHP_EOL;
curl_close($curl);
