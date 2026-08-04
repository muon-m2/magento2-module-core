<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Block\Adminhtml\Button;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Save and Continue Edit" — saves and stays on the form.
 *
 * Same mechanism note as {@see SaveButton}: this is the `buttonAdapter` variant, and it is not
 * interchangeable with a form wired for the legacy `['button' => ['event' => 'saveAndContinueEdit']]`
 * attribute.
 *
 * @api Referenced by FQCN (or by a virtual type built on it) from consuming ui_component form XML.
 */
class SaveAndContinueButton extends AbstractButton implements ButtonProviderInterface
{
    /**
     * @param \Magento\Backend\Block\Widget\Context $context
     * @param \Magento\Framework\Escaper $escaper
     * @param string $formNamespace The ui_component namespace, e.g. `muon_headermenu_item_form`.
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        private readonly string $formNamespace
    ) {
        parent::__construct($context, $escaper);
    }

    /**
     * Get the button definition.
     *
     * `params` is `[true, ['back' => 'edit']]`: redirect, and continue to the edit form. The pairing
     * is the point — a redirect flag without a destination is what makes a plain Save behave
     * unpredictably, which is why {@see SaveButton} passes false instead.
     *
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $target = sprintf('%s.%s', $this->formNamespace, $this->formNamespace);

        return [
            'label' => __('Save and Continue Edit'),
            'class' => 'save',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => $target,
                                'actionName' => 'save',
                                'params' => [true, ['back' => 'edit']],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 80,
        ];
    }
}
