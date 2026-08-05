<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Ui\DataProvider\Modifier;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Muon\Core\Api\CaptionStorageInterface;
use Muon\Core\Model\Caption\CaptionScope;
use Muon\Core\Ui\DataProvider\Modifier\StoreScopeFields;
use PHPUnit\Framework\TestCase;

class StoreScopeFieldsTest extends TestCase
{
    private const CAPTION_PATH = 'general/children/label';
    private const STRUCTURAL_PATH = 'general/children/url';

    /**
     * @return array<string,mixed>
     */
    private function meta(): array
    {
        return [
            'general' => [
                'children' => [
                    'label' => ['arguments' => ['data' => ['config' => ['dataType' => 'text']]]],
                    'url' => ['arguments' => ['data' => ['config' => ['dataType' => 'text']]]],
                ],
            ],
        ];
    }

    /**
     * @param int $storeId Zero for default scope.
     * @param bool $hasOverride
     * @return \Muon\Core\Ui\DataProvider\Modifier\StoreScopeFields
     */
    private function modifier(int $storeId, bool $hasOverride = false): StoreScopeFields
    {
        $scope = $this->createStub(CaptionScope::class);
        $scope->method('getStoreId')->willReturn($storeId);
        $scope->method('isDefaultScope')->willReturn($storeId === 0);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn(5);

        $storage = $this->createStub(CaptionStorageInterface::class);
        $storage->method('loadForStore')->willReturn($hasOverride ? [5 => 'Einkaufen'] : []);

        return new StoreScopeFields(
            new ArrayManager(),
            $request,
            $scope,
            $storage,
            'item_id',
            [self::CAPTION_PATH],
            [self::STRUCTURAL_PATH]
        );
    }

    /**
     * @param array<string,mixed> $meta
     * @param string $path
     * @return array<string,mixed>
     */
    private function config(array $meta, string $path): array
    {
        return (new ArrayManager())->get($path . '/arguments/data/config', $meta) ?? [];
    }

    /**
     * The property that makes this feature additive: an untouched form behaves exactly as before.
     */
    public function testDefaultScopeLeavesMetaByteForByteUnchanged(): void
    {
        $meta = $this->meta();

        self::assertSame($meta, $this->modifier(0)->modifyMeta($meta));
    }

    public function testStoreScopeAddsTheUseDefaultServiceTemplateToACaptionField(): void
    {
        $config = $this->config($this->modifier(2)->modifyMeta($this->meta()), self::CAPTION_PATH);

        self::assertSame(['template' => 'ui/form/element/helper/service'], $config['service']);
    }

    public function testStoreScopeLabelsTheCaptionFieldWithItsScope(): void
    {
        $config = $this->config($this->modifier(2)->modifyMeta($this->meta()), self::CAPTION_PATH);

        self::assertSame('[STORE VIEW]', $config['scopeLabel']);
    }

    /**
     * abstract.js does `isUseDefault(this.disabled())`, so `disabled` IS the checkbox's initial
     * state. No override means the merchant is on the default, so the box starts ticked.
     */
    public function testCaptionFieldStartsDisabledWhenTheStoreHasNoOverride(): void
    {
        $config = $this->config(
            $this->modifier(2, false)->modifyMeta($this->meta()),
            self::CAPTION_PATH
        );

        self::assertTrue($config['disabled']);
    }

    public function testCaptionFieldStartsEnabledWhenTheStoreAlreadyHasAnOverride(): void
    {
        $config = $this->config(
            $this->modifier(2, true)->modifyMeta($this->meta()),
            self::CAPTION_PATH
        );

        self::assertFalse($config['disabled']);
    }

    public function testStructuralFieldIsLockedInStoreScope(): void
    {
        $config = $this->config($this->modifier(2)->modifyMeta($this->meta()), self::STRUCTURAL_PATH);

        self::assertTrue($config['disabled']);
    }

    /**
     * Regression, inverted from an earlier design: this modifier must NEVER write a notice.
     *
     * Its return value is merged onto the XML-declared field, so a notice here would overwrite the
     * form's own — and several locked fields carry a security warning there ("Never use this to hide
     * confidential content"). There is no incoming meta to read the original back from, so the only
     * safe behaviour is to not write one at all.
     */
    public function testLockedFieldNeverGetsANoticeThatCouldOverwriteTheFormsOwn(): void
    {
        $config = $this->config($this->modifier(2)->modifyMeta($this->meta()), self::STRUCTURAL_PATH);

        self::assertArrayNotHasKey('notice', $config);
    }

    public function testStructuralFieldGetsNoUseDefaultCheckbox(): void
    {
        // Structure is global; offering "Use Default Value" on it would imply it can be overridden.
        $config = $this->config($this->modifier(2)->modifyMeta($this->meta()), self::STRUCTURAL_PATH);

        self::assertArrayNotHasKey('service', $config);
    }

    public function testExistingConfigOnAModifiedFieldIsPreserved(): void
    {
        $config = $this->config($this->modifier(2)->modifyMeta($this->meta()), self::CAPTION_PATH);

        self::assertSame('text', $config['dataType']);
    }

    /**
     * The modifier must emit config for a field that is absent from the incoming meta.
     *
     * This is the whole mechanism: an XML-declared form hands the data provider an EMPTY meta array,
     * and UiComponentFactory merges whatever comes back onto the XML bundle. A version that skipped
     * absent paths silently did nothing — no checkbox, nothing locked.
     */
    public function testConfigIsEmittedEvenWhenTheIncomingMetaIsEmpty(): void
    {
        $meta = $this->modifier(2)->modifyMeta([]);

        $config = $this->config($meta, self::CAPTION_PATH);

        self::assertSame(['template' => 'ui/form/element/helper/service'], $config['service']);
        self::assertTrue($this->config($meta, self::STRUCTURAL_PATH)['disabled']);
    }

    public function testModifyDataIsAPassThrough(): void
    {
        $data = [5 => ['label' => 'Shop']];

        self::assertSame($data, $this->modifier(2)->modifyData($data));
    }
}
