<?php

namespace eventiva\synmon\services;

use Craft;
use yii\base\Component;

class NotificationService extends Component
{
    public function notifyRun(int $runId, array $suite, ?array $failedStep, string $status): void
    {
        if ($status === 'pass' && !$suite['notifyOnSuccess']) {
            return;
        }

        $context = [
            'runId'       => $runId,
            'suite'       => $suite,
            'failedStep'  => $failedStep,
            'status'      => $status,
            'cpUrl'       => Craft::$app->config->general->cpTrigger ?? 'admin',
        ];

        if (!empty($suite['notifyEmail'])) {
            $this->sendEmail($suite, $context);
        }

        if (!empty($suite['notifyWebhookUrl'])) {
            $this->sendWebhook($suite['notifyWebhookUrl'], $context);
        }
    }

    public function sendEmail(array $suite, array $context): void
    {
        try {
            $subject = $context['status'] === 'pass'
                ? Craft::t('synmon', '[SynMon] ✅ Suite passed: {name}', ['name' => $suite['name']])
                : Craft::t('synmon', '[SynMon] ❌ Suite failed: {name}', ['name' => $suite['name']]);

            $body = $this->buildEmailBody($context);

            Craft::$app->getMailer()
                ->compose()
                ->setTo($suite['notifyEmail'])
                ->setSubject($subject)
                ->setHtmlBody($body)
                ->send();
        } catch (\Throwable $e) {
            Craft::error('SynMon NotificationService email failed: ' . $e->getMessage(), __METHOD__);
        }
    }

    public function sendWebhook(string $url, array $payload): void
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            Craft::error('SynMon NotificationService webhook failed: ' . $e->getMessage(), __METHOD__);
        }
    }

    private function buildEmailBody(array $context): string
    {
        $status    = $context['status'];
        $suite     = $context['suite'];
        $failedStep= $context['failedStep'];
        $runId     = $context['runId'];
        $icon      = $status === 'pass' ? '✅' : '❌';

        $html  = "<h2>{$icon} " . Craft::t('synmon', 'SynMon: Suite {status}', ['status' => $status]) . "</h2>";
        $html .= "<p><strong>" . Craft::t('synmon', 'Suite') . ":</strong> " . htmlspecialchars($suite['name']) . "</p>";
        $html .= "<p><strong>" . Craft::t('synmon', 'Status') . ":</strong> " . strtoupper($status) . "</p>";
        $html .= "<p><strong>" . Craft::t('synmon', 'Run ID') . ":</strong> #{$runId}</p>";
        $html .= "<p><strong>" . Craft::t('synmon', 'Time') . ":</strong> " . date('Y-m-d H:i:s') . "</p>";

        if ($failedStep) {
            $html .= "<hr>";
            $html .= "<h3>" . Craft::t('synmon', 'Failed Step') . "</h3>";
            $html .= "<p><strong>" . Craft::t('synmon', 'Type') . ":</strong> " . htmlspecialchars($failedStep['type']) . "</p>";
            if (!empty($failedStep['selector'])) {
                $html .= "<p><strong>Selector:</strong> <code>" . htmlspecialchars($failedStep['selector']) . "</code></p>";
            }
            if (!empty($failedStep['value'])) {
                $html .= "<p><strong>" . Craft::t('synmon', 'Value') . ":</strong> <code>" . htmlspecialchars($failedStep['value']) . "</code></p>";
            }
            if (!empty($failedStep['description'])) {
                $html .= "<p><strong>" . Craft::t('synmon', 'Description') . ":</strong> " . htmlspecialchars($failedStep['description']) . "</p>";
            }
        }

        return $html;
    }
}
