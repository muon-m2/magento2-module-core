<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Block\Adminhtml\Button;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;

/**
 * Shared plumbing for an adminhtml edit-form button.
 *
 * Holds the two things every button in every Muon admin form needed and each module had reimplemented:
 * a URL builder and a JS escaper. It deliberately does NOT resolve an entity id — that is the one
 * genuinely per-entity concern (a repository lookup here, a raw request param there), so it stays in
 * the module's own GenericButton.
 */
abstract class AbstractButton
{
    /**
     * @param \Magento\Backend\Block\Widget\Context $context
     * @param \Magento\Framework\Escaper $escaper
     */
    public function __construct(
        protected readonly Context $context,
        protected readonly Escaper $escaper
    ) {
    }

    /**
     * Build an admin URL.
     *
     * @param string $route
     * @param mixed[] $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }

    /**
     * Escape a value for interpolation into a JavaScript string literal.
     *
     * @param string $value
     * @return string
     */
    protected function escapeJs(string $value): string
    {
        return $this->escaper->escapeJs($value);
    }
}
