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
                ? '[SynMon] ✅ Suite passed: ' . $suite['name']
                : '[SynMon] ❌ Suite failed: ' . $suite['name'];

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

        $html  = "<h2>{$icon} SynMon: Suite {$status}</h2>";
        $html .= "<p><strong>Suite:</strong> " . htmlspecialchars($suite['name']) . "</p>";
        $html .= "<p><strong>Status:</strong> " . strtoupper($status) . "</p>";
        $html .= "<p><strong>Run ID:</strong> #{$runId}</p>";
        $html .= "<p><strong>Zeit:</strong> " . date('d.m.Y H:i:s') . "</p>";

        if ($failedStep) {
            $html .= "<hr>";
            $html .= "<h3>Fehlgeschlagener Schritt</h3>";
            $html .= "<p><strong>Typ:</strong> " . htmlspecialchars($failedStep['type']) . "</p>";
            if (!empty($failedStep['selector'])) {
                $html .= "<p><strong>Selector:</strong> <code>" . htmlspecialchars($failedStep['selector']) . "</code></p>";
            }
            if (!empty($failedStep['value'])) {
                $html .= "<p><strong>Value:</strong> <code>" . htmlspecialchars($failedStep['value']) . "</code></p>";
            }
            if (!empty($failedStep['description'])) {
                $html .= "<p><strong>Beschreibung:</strong> " . htmlspecialchars($failedStep['description']) . "</p>";
            }
        }

        return $html;
    }
}
