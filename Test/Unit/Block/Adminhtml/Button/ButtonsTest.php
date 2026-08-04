<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Block\Adminhtml\Button;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Muon\Core\Block\Adminhtml\Button\BackButton;
use Muon\Core\Block\Adminhtml\Button\SaveAndContinueButton;
use Muon\Core\Block\Adminhtml\Button\SaveButton;
use PHPUnit\Framework\TestCase;

class ButtonsTest extends TestCase
{
    private Context $context;
    private Escaper $escaper;

    protected function setUp(): void
    {
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params = []): string => 'https://admin.test/' . trim($route, '*/')
        );

        $this->context = $this->createStub(Context::class);
        $this->context->method('getUrlBuilder')->willReturn($urlBuilder);

        $this->escaper = $this->createStub(Escaper::class);
        // Stand in for the real escaper with something observable: a quote must not survive.
        $this->escaper->method('escapeJs')->willReturnCallback(
            static fn (string $v): string => str_replace("'", '\\u0027', $v)
        );
    }

    public function testBackButtonReturnsToTheListing(): void
    {
        $data = (new BackButton($this->context, $this->escaper))->getButtonData();

        self::assertSame('back', $data['class']);
        self::assertSame(10, $data['sort_order']);
        self::assertStringContainsString('location.href', $data['on_click']);
    }

    /**
     * The URL reaches a JS string literal, so it must go through the escaper — not be interpolated raw.
     */
    public function testBackButtonEscapesTheUrlItInterpolates(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->expects(self::once())->method('escapeJs')->willReturn('ESCAPED');

        $data = (new BackButton($this->context, $escaper))->getButtonData();

        self::assertSame("location.href = 'ESCAPED';", $data['on_click']);
    }

    public function testSaveButtonTargetsTheConfiguredFormAndDoesNotRequestARedirect(): void
    {
        $data = (new SaveButton($this->context, $this->escaper, 'acme_widget_form', 'Save Widget'))
            ->getButtonData();

        $action = $data['data_attribute']['mage-init']['buttonAdapter']['actions'][0];

        self::assertSame('acme_widget_form.acme_widget_form', $action['targetName']);
        self::assertSame('save', $action['actionName']);
        // False, not true: a plain Save must not ask for a redirect without naming a destination.
        self::assertSame([false], $action['params']);
        self::assertSame('save primary', $data['class']);
        self::assertSame(90, $data['sort_order']);
    }

    public function testSaveButtonLabelDefaultsButIsOverridable(): void
    {
        $default = (new SaveButton($this->context, $this->escaper, 'acme_widget_form'))->getButtonData();
        $custom = (new SaveButton($this->context, $this->escaper, 'acme_widget_form', 'Save Widget'))
            ->getButtonData();

        self::assertSame('Save', (string) $default['label']);
        self::assertSame('Save Widget', (string) $custom['label']);
    }

    public function testSaveAndContinuePairsTheRedirectFlagWithADestination(): void
    {
        $data = (new SaveAndContinueButton($this->context, $this->escaper, 'acme_widget_form'))
            ->getButtonData();

        $action = $data['data_attribute']['mage-init']['buttonAdapter']['actions'][0];

        self::assertSame('acme_widget_form.acme_widget_form', $action['targetName']);
        self::assertSame([true, ['back' => 'edit']], $action['params']);
        self::assertSame(80, $data['sort_order']);
    }

    /**
     * The two save buttons must not collide in the button row.
     */
    public function testSaveButtonsSortAfterBackAndInAStableOrder(): void
    {
        $back = (new BackButton($this->context, $this->escaper))->getButtonData();
        $continue = (new SaveAndContinueButton($this->context, $this->escaper, 'f'))->getButtonData();
        $save = (new SaveButton($this->context, $this->escaper, 'f'))->getButtonData();

        self::assertLessThan($continue['sort_order'], $back['sort_order']);
        self::assertLessThan($save['sort_order'], $continue['sort_order']);
    }
}
