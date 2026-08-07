<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Muon\Core\Api\VisitorContextInterface;

/**
 * Who is looking.
 *
 * EVERY VALUE COMES FROM App\Http\Context. Reading the customer session instead would be wrong twice
 * over: on a cacheable page Customer\Model\Layout\DepersonalizePlugin empties that session after
 * layout generation, so the value would be unstable; and only HTTP-context values reach
 * Context::getVaryString(), which keys the full-page cache. Group-specific content built from the
 * session would be cached once and served to every group — the exact leak the filters exist to
 * prevent. Do not "simplify" this to a session read.
 *
 * Shared by the object manager and therefore memoised per request and reset between them.
 */
class VisitorContext implements VisitorContextInterface, ResetAfterRequestInterface
{
    /**
     * Memoised store ID for this request.
     */
    private ?int $storeId = null;

    /**
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly HttpContext $httpContext,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        if ($this->storeId === null) {
            try {
                $this->storeId = (int) $this->storeManager->getStore()->getId();
            } catch (NoSuchEntityException) {
                // No resolvable store means no content rather than a fatal in a page fragment.
                // Admin-scope callers (a schedule cron, a config save) legitimately land here.
                $this->storeId = 0;
            }
        }

        return $this->storeId;
    }

    /**
     * @inheritDoc
     */
    public function getCustomerGroupId(): int
    {
        $value = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

        // The default is NOT_LOGGED_IN rather than 0-as-unknown: group 0 IS that group, so there is
        // no "unknown group" state to represent — a request without the context value is a guest.
        return $value === null ? (int) GroupManagement::NOT_LOGGED_IN_ID : (int) $value;
    }

    /**
     * @inheritDoc
     */
    public function isLoggedIn(): bool
    {
        return (bool) $this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    /**
     * @inheritDoc
     */
    public function getCacheKeySuffix(): string
    {
        return sprintf(
            's%d_g%d_a%d',
            $this->getStoreId(),
            $this->getCustomerGroupId(),
            $this->isLoggedIn() ? 1 : 0
        );
    }

    /**
     * Clear the per-request store memo.
     *
     * @return void
     */
    public function _resetState(): void
    {
        $this->storeId = null;
    }
}
