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
 * "Save" — the form's primary action, driven by the UI-component `buttonAdapter`.
 *
 * The label and the form namespace are the only things that varied across the copies this replaces,
 * so both arrive as DI arguments and each consumer declares a virtual type rather than a subclass.
 *
 * ONLY FOR FORMS ON THE `buttonAdapter` MECHANISM. A form still using the legacy
 * `['button' => ['event' => 'save']]` + `form-role` attributes needs its own button — the two are
 * different wiring, not different labels, and this class would silently do nothing on such a form.
 *
 * @api Referenced by FQCN (or by a virtual type built on it) from consuming ui_component form XML.
 */
class SaveButton extends AbstractButton implements ButtonProviderInterface
{
    /**
     * @param \Magento\Backend\Block\Widget\Context $context
     * @param \Magento\Framework\Escaper $escaper
     * @param string $formNamespace The ui_component namespace, e.g. `muon_headermenu_item_form`.
     * @param string $label Button label, passed through __() at render so it stays translatable.
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        private readonly string $formNamespace,
        private readonly string $label = 'Save'
    ) {
        parent::__construct($context, $escaper);
    }

    /**
     * Get the button definition.
     *
     * `params[0]` is the form's `redirect` flag, and it is FALSE here deliberately. Magento core's
     * own plain Save passes false and lets the controller decide where to go; true is for the
     * save-and-continue family, which pairs it with a `back` value saying where to continue to.
     * Passing true with no such value asks for a redirect without saying where — see
     * Magento\Cms\Block\Adminhtml\Page\Edit\SaveButton for the core precedent.
     *
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $target = sprintf('%s.%s', $this->formNamespace, $this->formNamespace);

        return [
            'label' => __($this->label),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => $target,
                                'actionName' => 'save',
                                'params' => [false],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 90,
        ];
    }
}
