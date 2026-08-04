<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Block\Adminhtml\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Back" — returns to the listing.
 *
 * Concrete rather than abstract: this button has no per-entity variation at all. It was carried
 * five times across three modules, byte-identical apart from the namespace, and one of those copies
 * escaped the URL while the other four did not. The escaping version is the one kept — see below.
 *
 * @api Referenced by FQCN from every consuming module's ui_component form XML, so the class name is
 *      part of the contract; renaming it breaks those forms.
 */
class BackButton extends AbstractButton implements ButtonProviderInterface
{
    /**
     * Get the button definition.
     *
     * The URL is escaped before it reaches the `location.href = '…'` literal. It comes from the URL
     * builder rather than from input, so nothing here is attacker-controlled today — but a value
     * interpolated into a JS string is escaped on principle, not on a case-by-case judgement about
     * whether this particular caller happens to be safe.
     *
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Back'),
            'on_click' => sprintf("location.href = '%s';", $this->escapeJs($this->getUrl('*/*/'))),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
