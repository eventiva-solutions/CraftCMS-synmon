<?php

namespace eventiva\synmon\services;

use Craft;
use craft\helpers\StringHelper;
use eventiva\synmon\records\StepRecord;
use eventiva\synmon\records\SuiteRecord;
use yii\base\Component;

class SuiteService extends Component
{
    public function getSuites(): array
    {
        return SuiteRecord::find()->orderBy('name ASC')->asArray()->all();
    }

    public function getSuiteById(int $id): ?array
    {
        $record = SuiteRecord::findOne($id);
        return $record ? $record->toArray() : null;
    }

    public function createSuite(array $data): int|false
    {
        $record = new SuiteRecord();
        $record->uid              = StringHelper::UUID();
        $record->name             = $data['name'] ?? Craft::t('synmon', 'New Suite');
        $record->description      = $data['description'] ?? null;
        $record->cronExpression   = $data['cronExpression'] ?? '*/5 * * * *';
        $record->enabled          = (bool)($data['enabled'] ?? true);
        $record->notifyEmail      = $data['notifyEmail'] ?? null;
        $record->notifyWebhookUrl = $data['notifyWebhookUrl'] ?? null;
        $record->notifyOnSuccess  = (bool)($data['notifyOnSuccess'] ?? false);

        if ($record->save()) {
            return $record->id;
        }

        Craft::error('SuiteService::createSuite failed: ' . json_encode($record->errors), __METHOD__);
        return false;
    }

    public function updateSuite(int $id, array $data): bool
    {
        $record = SuiteRecord::findOne($id);
        if (!$record) {
            return false;
        }

        $record->name             = $data['name'] ?? $record->name;
        $record->description      = $data['description'] ?? $record->description;
        $record->cronExpression   = $data['cronExpression'] ?? $record->cronExpression;
        $record->enabled          = (bool)($data['enabled'] ?? $record->enabled);
        $record->notifyEmail      = $data['notifyEmail'] ?? $record->notifyEmail;
        $record->notifyWebhookUrl = $data['notifyWebhookUrl'] ?? $record->notifyWebhookUrl;
        $record->notifyOnSuccess  = (bool)($data['notifyOnSuccess'] ?? $record->notifyOnSuccess);

        if ($record->save()) {
            return true;
        }

        Craft::error('SuiteService::updateSuite failed: ' . json_encode($record->errors), __METHOD__);
        return false;
    }

    public function deleteSuite(int $id): bool
    {
        $record = SuiteRecord::findOne($id);
        if (!$record) {
            return false;
        }
        return (bool)$record->delete();
    }

    public function getStepsBySuiteId(int $suiteId): array
    {
        return StepRecord::find()
            ->where(['suiteId' => $suiteId])
            ->orderBy('sortOrder ASC')
            ->asArray()
            ->all();
    }

    public function saveSteps(int $suiteId, array $steps): void
    {
        StepRecord::deleteAll(['suiteId' => $suiteId]);

        foreach ($steps as $index => $stepData) {
            $record              = new StepRecord();
            $record->uid         = StringHelper::UUID();
            $record->suiteId     = $suiteId;
            $record->sortOrder   = (int)($stepData['sortOrder'] ?? $index);
            $record->type        = $stepData['type'] ?? 'navigate';
            $record->selector    = $stepData['selector'] ?? null;
            $record->value       = $stepData['value'] ?? null;
            $record->description = $stepData['description'] ?? null;
            $record->timeout     = (int)($stepData['timeout'] ?? 30000);
            $record->save();
        }
    }

    public function cloneSuite(int $id): int|false
    {
        $source = SuiteRecord::findOne($id);
        if (!$source) {
            return false;
        }

        $clone              = new SuiteRecord();
        $clone->uid         = StringHelper::UUID();
        $clone->name        = $source->name . Craft::t('synmon', ' (Copy)');
        $clone->description = $source->description;
        $clone->cronExpression   = $source->cronExpression;
        $clone->enabled          = false; // disabled by default so cron doesn't run it immediately
        $clone->notifyEmail      = $source->notifyEmail;
        $clone->notifyWebhookUrl = $source->notifyWebhookUrl;
        $clone->notifyOnSuccess  = $source->notifyOnSuccess;

        if (!$clone->save()) {
            return false;
        }

        $steps = StepRecord::find()->where(['suiteId' => $id])->orderBy('sortOrder ASC')->all();
        foreach ($steps as $step) {
            $newStep              = new StepRecord();
            $newStep->uid         = StringHelper::UUID();
            $newStep->suiteId     = $clone->id;
            $newStep->sortOrder   = $step->sortOrder;
            $newStep->type        = $step->type;
            $newStep->selector    = $step->selector;
            $newStep->value       = $step->value;
            $newStep->description = $step->description;
            $newStep->timeout     = $step->timeout;
            $newStep->save();
        }

        return $clone->id;
    }

    public function updateLastRunStatus(int $suiteId, string $status): void
    {
        Craft::$app->getDb()->createCommand()->update(
            '{{%synmon_suites}}',
            [
                'lastRunAt'     => (new \DateTime())->format('Y-m-d H:i:s'),
                'lastRunStatus' => $status,
                'dateUpdated'   => (new \DateTime())->format('Y-m-d H:i:s'),
            ],
            ['id' => $suiteId]
        )->execute();
    }

    public function getStepTypes(): array
    {
        $selectorTips = '<br><br><b>Selector tips (general):</b><br>'
            . '• <code>#my-id</code> → element with ID<br>'
            . '• <code>.my-class</code> → element with class<br>'
            . '• <code>input[name="firstname"]</code> → attribute selector<br>'
            . '• <code>input[name="items[]"][value="Option"]</code> → checkbox with specific value<br>'
            . '• <code>form .btn-primary</code> → child element (space)<br>'
            . '• <code>h2 + p</code> → immediately following sibling<br>'
            . '• <code>li:first-child</code> / <code>li:last-child</code> → first/last child<br>'
            . '• <code>p:nth-of-type(2)</code> → 2nd element of that type<br>'
            . '• <code>[data-fui-id*="contact"]</code> → attribute contains value (useful for dynamic IDs)';

        return [
            'navigate' => [
                'label' => Craft::t('synmon', 'Navigate'), 'hasSelector' => false, 'hasValue' => true,
                'valuePlaceholder' => 'https://example.com/contact',
                'hint' => 'Opens a URL in the browser and waits until the page has fully loaded.<br><b>Value:</b> Full URL including <code>https://</code><br><b>Tip:</b> Every test should start with a Navigate step.',
            ],
            'click' => [
                'label' => Craft::t('synmon', 'Click'), 'hasSelector' => true, 'hasValue' => false,
                'selectorPlaceholder' => 'button[type="submit"]',
                'hint' => 'Clicks an element.<br><b>Selector:</b> CSS selector of the element<br><b>Examples:</b><br>• <code>button[type="submit"]</code> → submit button<br>• <code>#nav-contact</code> → link with ID<br>• <code>.btn-primary</code> → button with class<br>• <code>a[href="/contact"]</code> → link to a specific URL<br>• <code>nav li:last-child a</code> → last nav link' . $selectorTips,
            ],
            'fill' => [
                'label' => Craft::t('synmon', 'Fill Input'), 'hasSelector' => true, 'hasValue' => true,
                'selectorPlaceholder' => 'input[name="fields[firstname]"]',
                'valuePlaceholder' => 'John Doe',
                'hint' => 'Types text into an input field (overwrites existing content).<br><b>Selector:</b> CSS selector of the input field<br><b>Examples:</b><br>• <code>input[name="fields[firstname]"]</code> → Craft Freeform field<br>• <code>input[type="email"]</code> → email field<br>• <code>textarea[name="fields[message]"]</code> → text area<br>• <code>#search-input</code> → search field with ID<br><b>Value:</b> The text to enter' . $selectorTips,
            ],
            'select' => [
                'label' => Craft::t('synmon', 'Select Option'), 'hasSelector' => true, 'hasValue' => true,
                'selectorPlaceholder' => 'select[name="fields[salutation]"]',
                'valuePlaceholder' => 'Mr',
                'hint' => 'Selects an option in a <code>&lt;select&gt;</code> dropdown.<br><b>Selector:</b> CSS selector of the <code>&lt;select&gt;</code> element<br><b>Examples:</b><br>• <code>select[name="fields[salutation]"]</code> → Craft Freeform select<br>• <code>select#country</code> → select with ID<br><b>Value:</b> The <code>value</code> attribute of the option – <i>not</i> the displayed text' . $selectorTips,
            ],
            'pressKey' => [
                'label' => Craft::t('synmon', 'Press Key'), 'hasSelector' => false, 'hasValue' => true,
                'valuePlaceholder' => 'Enter',
                'hint' => 'Presses a key on the keyboard (acts on the currently focused element).<br><b>Value:</b> <code>Enter</code>, <code>Tab</code>, <code>Escape</code>, <code>Space</code>, <code>Backspace</code>, <code>Delete</code>, <code>ArrowDown</code>, <code>ArrowUp</code>, <code>ArrowLeft</code>, <code>ArrowRight</code><br><b>Tip:</b> After a <code>fill</code> step, <code>Enter</code> can submit a form.',
            ],
            'assertVisible' => [
                'label' => Craft::t('synmon', 'Assert Visible'), 'hasSelector' => true, 'hasValue' => false,
                'selectorPlaceholder' => '.success-message',
                'hint' => 'Checks if an element is visible. Fails if it does not exist or is hidden via CSS.<br><b>Examples:</b><br>• <code>.success-message</code> → success message after form submit<br>• <code>#cookie-banner</code> → cookie banner visible<br>• <code>.product-grid .item</code> → at least one product present<br><b>Tip:</b> Useful to verify a message or section appears after click/submit' . $selectorTips,
            ],
            'assertText' => [
                'label' => Craft::t('synmon', 'Assert Text'), 'hasSelector' => true, 'hasValue' => true,
                'selectorPlaceholder' => '.success-message, h1, #output',
                'valuePlaceholder' => 'Thank you (substring is enough)',
                'hint' => 'Waits until an element contains the expected text (polls every 250ms until timeout, incl. Shadow DOM).<br><b>Selector:</b> If multiple elements match, it passes if <i>any</i> of them contains the text<br><b>Value:</b> Text to find (substring, case-sensitive)<br><b>Examples:</b><br>• <code>.fui-alert</code> → Freeform success/error message<br>• <code>h1</code> → page heading<br>• <code>.message:last-child p</code> → last paragraph of the last message<br>• <code>.message p:nth-of-type(2)</code> → exactly the 2nd paragraph<br><b>⚠️ If "text visible on page but not found":</b> Selector too narrow → broaden it, e.g. <code>.message</code> instead of <code>.message span</code>' . $selectorTips,
            ],
            'assertUrl' => [
                'label' => Craft::t('synmon', 'Assert URL'), 'hasSelector' => false, 'hasValue' => true,
                'valuePlaceholder' => '/thank-you or ?success=1',
                'hint' => 'Checks if the current page URL contains a specific string.<br><b>Value:</b> URL substring to find<br><b>Examples:</b><br>• <code>/thank-you</code> → redirect after form submit<br>• <code>/success</code> → success page<br>• <code>?status=ok</code> → query parameter<br>• <code>example.com/contact</code> → exact domain + path',
            ],
            'assertTitle' => [
                'label' => Craft::t('synmon', 'Assert Title'), 'hasSelector' => false, 'hasValue' => true,
                'valuePlaceholder' => 'Contact – My Website',
                'hint' => 'Checks if the <code>&lt;title&gt;</code> tag of the page contains a specific string.<br><b>Value:</b> Substring of the page title to find<br><b>Tip:</b> Useful to verify the correct page was loaded.',
            ],
            'waitForSelector' => [
                'label' => Craft::t('synmon', 'Wait for Selector'), 'hasSelector' => true, 'hasValue' => false,
                'selectorPlaceholder' => '.search-results, #chat-widget',
                'hint' => 'Waits until an element appears in the DOM and is visible.<br><b>Examples:</b><br>• <code>.search-results</code> → results after AJAX load<br>• <code>#chat-widget</code> → widget appears after delay<br>• <code>.fui-form</code> → Freeform form loaded<br><b>Tip:</b> Increase timeout for content that takes long to load' . $selectorTips,
            ],
            'assertNotVisible' => [
                'label' => Craft::t('synmon', 'Assert Not Visible'), 'hasSelector' => true, 'hasValue' => false,
                'selectorPlaceholder' => '.modal, #cookie-banner, .spinner',
                'hint' => 'Checks if an element is <b>not visible</b> or has been removed from the DOM.<br><b>Examples:</b><br>• <code>.modal</code> → modal was closed<br>• <code>#cookie-banner</code> → banner gone after accepting<br>• <code>.loading-spinner</code> → loading animation finished<br>• <code>.error-message</code> → no error present' . $selectorTips,
            ],
            'hover' => [
                'label' => Craft::t('synmon', 'Hover'), 'hasSelector' => true, 'hasValue' => false,
                'selectorPlaceholder' => 'nav .has-dropdown',
                'hint' => 'Moves the mouse over an element (hover effect).<br><b>Examples:</b><br>• <code>nav .has-dropdown</code> → open dropdown navigation<br>• <code>.product-card:first-child</code> → hover on first product<br>• <code>[data-tooltip]</code> → tooltip element<br><b>Tip:</b> After hover, immediately add <code>waitForSelector</code> for the appearing element' . $selectorTips,
            ],
            'scroll' => [
                'label' => Craft::t('synmon', 'Scroll'), 'hasSelector' => true, 'hasValue' => true,
                'selectorPlaceholder' => '#contact-form (optional)',
                'valuePlaceholder' => '500 (pixels, negative = up)',
                'hint' => 'Scrolls the page or brings an element into view.<br><b>Selector (optional):</b> Element to scroll into view<br><b>Value (without selector):</b> Pixels, e.g. <code>500</code> (down), <code>-200</code> (up)<br><b>Examples:</b><br>• Selector <code>#contact-form</code> → scrolls to the form<br>• Selector <code>footer</code> → scrolls to the footer<br>• Value only <code>800</code> → scrolls 800px down<br><b>Tip:</b> Lazy-loaded images/content only load after scrolling' . $selectorTips,
            ],
            'waitMs' => [
                'label' => Craft::t('synmon', 'Wait (ms)'), 'hasSelector' => false, 'hasValue' => true,
                'valuePlaceholder' => '1000',
                'hint' => 'Waits a fixed number of milliseconds.<br><b>Value:</b> Wait time in ms, e.g. <code>500</code> = 0.5s · <code>1000</code> = 1s · <code>3000</code> = 3s<br><b>⚠️ Tip:</b> Prefer <code>waitForSelector</code> or <code>assertText</code> where possible – fixed waits are fragile under varying server speeds.',
            ],
            'assertCount' => [
                'label' => Craft::t('synmon', 'Assert Count'), 'hasSelector' => true, 'hasValue' => true,
                'selectorPlaceholder' => '.product-card, li.result',
                'valuePlaceholder' => '3',
                'hint' => 'Checks if exactly N elements match the selector.<br><b>Selector:</b> CSS selector<br><b>Value:</b> Expected count (exact)<br><b>Examples:</b><br>• <code>.nav-item</code> + <code>5</code> → exactly 5 navigation items<br>• <code>.product-card</code> + <code>12</code> → 12 products loaded<br>• <code>table tbody tr</code> + <code>10</code> → 10 table rows' . $selectorTips,
            ],
            'check' => [
                'label' => Craft::t('synmon', 'Check Checkbox'), 'hasSelector' => true, 'hasValue' => false,
                'selectorPlaceholder' => 'input[name="fields[terms]"]',
                'hint' => 'Sets a checkbox or radio button to <b>checked</b> (works with styled checkboxes that have a label overlay).<br><b>Examples:</b><br>• <code>input[name="fields[terms]"]</code> → terms checkbox (Craft Freeform)<br>• <code>input[name="fields[tech][]"][value="Audio"]</code> → checkbox group with value<br>• <code>input[type="radio"][value="yes"]</code> → radio button<br>• <code>[data-fui-id*="newsletter"]</code> → element with dynamic ID (contains "newsletter")' . $selectorTips,
            ],
            'uncheck' => [
                'label' => Craft::t('synmon', 'Uncheck Checkbox'), 'hasSelector' => true, 'hasValue' => false,
                'selectorPlaceholder' => 'input[name="fields[newsletter]"]',
                'hint' => 'Sets a checkbox to <b>unchecked</b>.<br><b>Examples:</b><br>• <code>input[name="fields[newsletter]"]</code> → newsletter opt-in<br>• <code>input[name="fields[tech][]"][value="Audio"]</code> → checkbox group with value' . $selectorTips,
            ],
        ];
    }
}
